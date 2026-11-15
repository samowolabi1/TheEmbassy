<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Fellowship;
use App\Models\Member;
use App\Models\Shop;
use App\Models\Unit;
use App\Services\Messaging\Campaigns;
use App\Services\Messaging\WebPush;
use App\Services\Messaging\WhatsApp;
use App\Support\Notify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Message centre: everyone the church can reach in one list, and messages to members,
 * sellers, staff or everyone, by email, WhatsApp and app notification.
 */
class OutreachController extends Controller
{
    public function index(): View
    {
        return view('admin.outreach.index', [
            'campaigns' => Campaign::with('creator')->latest()->paginate(20),
            'counts' => [
                'members' => Member::where('status', Member::ACTIVE)->count(),
                'sellers' => Shop::approved()->count(),
                'sent' => Campaign::where('status', 'sent')->where('finished_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function create(Request $request, Campaigns $campaigns): View
    {
        $campaign = new Campaign([
            'channels' => ['email'],
            'audience' => Campaigns::audience(['type' => $request->query('to', 'members')]),
        ]);

        return $this->form($campaign, $campaigns);
    }

    public function edit(Campaign $campaign, Campaigns $campaigns): View|RedirectResponse
    {
        if ($campaign->status !== 'draft') {
            return redirect()->route('admin.outreach.show', $campaign);
        }

        return $this->form($campaign, $campaigns);
    }

    public function store(Request $request, Campaigns $campaigns): RedirectResponse
    {
        return $this->save($request, new Campaign, $campaigns);
    }

    public function update(Request $request, Campaign $campaign, Campaigns $campaigns): RedirectResponse
    {
        abort_unless($campaign->status === 'draft', 422, 'This message has already gone out.');

        return $this->save($request, $campaign, $campaigns);
    }

    public function show(Request $request, Campaign $campaign): View
    {
        $filter = in_array($request->query('show'), ['sent', 'failed', 'pending'], true) ? $request->query('show') : null;

        return view('admin.outreach.show', [
            'campaign' => $campaign,
            'recipients' => $campaign->recipients()->when($filter, fn ($q) => $q->where('status', $filter))->orderBy('id')->paginate(50)->withQueryString(),
            'filter' => $filter,
        ]);
    }

    public function cancel(Campaign $campaign): RedirectResponse
    {
        abort_unless(in_array($campaign->status, ['scheduled', 'sending'], true), 422, 'Only messages that haven\'t finished going out can be stopped.');
        $campaign->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
        $campaign->recipients()->where('status', 'pending')->delete();

        return back()->with('status', 'Stopped. Nobody else will get it.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status === 'draft', 422, 'Only drafts can be deleted.');
        $campaign->delete();

        return redirect()->route('admin.outreach.index')->with('status', 'Draft deleted.');
    }

    /**
     * How many people the audience chosen on the form reaches, for the live count.
     */
    public function reach(Request $request, Campaigns $campaigns): JsonResponse
    {
        return response()->json($campaigns->reach(Campaigns::audience($request->query())));
    }

    /**
     * Everyone the church can reach, in one searchable list, with the messages each has had.
     */
    public function contacts(Request $request, Campaigns $campaigns): View
    {
        $q = trim((string) $request->query('q'));
        $type = array_key_exists($request->query('type'), Campaigns::AUDIENCES) ? $request->query('type') : 'everyone';
        $people = $campaigns->contacts(Campaigns::audience(['type' => $type, 'member_status' => 'all', 'seller_status' => 'all']))
            ->when($q !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p['name'].' '.$p['email'].' '.$p['phone']), mb_strtolower($q))))
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $slice = $people->forPage($page, 40)->values();
        $history = CampaignRecipient::whereIn('contact_id', $slice->pluck('contact_id'))->where('status', 'sent')
            ->selectRaw('kind, contact_id, count(*) as messages, max(sent_at) as last_at')->groupBy('kind', 'contact_id')->get()
            ->keyBy(fn ($r) => $r->kind.':'.$r->contact_id);

        return view('admin.outreach.contacts', [
            'people' => new LengthAwarePaginator($slice, $people->count(), 40, $page, ['path' => $request->url(), 'query' => $request->query()]),
            'history' => $history,
            'q' => $q,
            'type' => $type,
            'totals' => $people->countBy('kind'),
        ]);
    }

    private function form(Campaign $campaign, Campaigns $campaigns): View
    {
        return view('admin.outreach.form', [
            'campaign' => $campaign,
            'units' => Unit::orderBy('name')->get(['id', 'name']),
            'fellowships' => Fellowship::orderBy('name')->get(['id', 'name']),
            'ways' => $this->ways(),
            'reach' => $campaigns->reach($campaign->audience ?? Campaigns::audience([])),
        ]);
    }

    /**
     * @return array<string, array{label: string, ready: bool}>
     */
    private function ways(): array
    {
        return [
            'email' => ['label' => 'Email', 'ready' => Notify::on('email')],
            'whatsapp' => ['label' => 'WhatsApp', 'ready' => app(WhatsApp::class)->ready()],
            'push' => ['label' => 'App notification', 'ready' => Notify::on('push') && app(WebPush::class)->configured()],
        ];
    }

    private function save(Request $request, Campaign $campaign, Campaigns $campaigns): RedirectResponse
    {
        $ready = array_keys(array_filter($this->ways(), fn ($w) => $w['ready']));
        $action = in_array($request->input('action'), ['draft', 'test', 'send', 'schedule'], true) ? $request->input('action') : 'draft';
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'channels' => [$action === 'draft' ? 'nullable' : 'required', 'array'],
            'channels.*' => [Rule::in($ready)],
            'scheduled_at' => [$action === 'schedule' ? 'required' : 'nullable', 'date', 'after:now'],
            'audience' => ['array'],
        ], [
            'channels.required' => 'Choose at least one way to send it.',
            'channels.*.in' => 'That way of sending is switched off or isn\'t set up yet (Settings & roles › Notifications).',
            'scheduled_at.after' => 'Choose a time in the future.',
        ]);

        $campaign->fill([
            'subject' => $data['subject'],
            'body' => $data['body'],
            'audience' => Campaigns::audience($data['audience'] ?? []),
            'channels' => array_values(array_unique($data['channels'] ?? [])),
            'scheduled_at' => $action === 'schedule' ? $data['scheduled_at'] : null,
        ]);
        $campaign->created_by ??= $request->user()->id;
        $campaign->save();

        if ($action === 'test') {
            $went = $campaigns->test($campaign, $request->user());

            return redirect()->route('admin.outreach.edit', $campaign)->with('status', $went ? 'Test sent to you by '.implode(' and ', $went).'.' : 'Nothing could be sent to you: tick a way of sending, and check your account has an email (or phone for WhatsApp).');
        }
        if ($action === 'draft') {
            return redirect()->route('admin.outreach.edit', $campaign)->with('status', 'Draft saved.');
        }

        $reach = $campaigns->reach($campaign->audience)['people'];
        if ($reach === 0) {
            return redirect()->route('admin.outreach.edit', $campaign)->withErrors(['audience' => 'Nobody matches who it\'s for. Change the choices and try again.']);
        }
        $campaigns->launch($campaign);

        return redirect()->route('admin.outreach.show', $campaign)->with('status', $campaign->status === 'scheduled'
            ? 'Scheduled for '.$campaign->scheduled_at->format('D j M, g:i A').'. It goes to everyone who matches then.'
            : "Sending to {$reach} people now. It goes out over the next few minutes; this page shows progress.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\User;
use App\Models\Visit;
use App\Services\Care\CareNotices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Visitation: plan visits to members and visitors, choose who goes, and record how each went.
 */
class VisitController extends Controller
{
    private const TABS = ['upcoming' => 'Coming up', 'mine' => 'My visits', 'done' => 'Done', 'cancelled' => 'Cancelled'];

    public function index(Request $request, CareNotices $notices): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'upcoming';
        $me = $request->user();
        $query = fn () => Visit::query();
        $filter = fn ($q, string $t) => match ($t) {
            'mine' => $q->planned()->whereHas('visitors', fn ($v) => $v->whereKey($me->id)),
            'done' => $q->where('status', 'done'),
            'cancelled' => $q->where('status', 'cancelled'),
            default => $q->planned(),
        };
        $followUp = $request->integer('follow_up') ? FollowUp::with('member', 'firstTimer')->open()->find($request->integer('follow_up')) : null;

        return view('admin.visits', [
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => ['label' => $label, 'count' => in_array($key, ['upcoming', 'mine'], true) ? $filter($query(), $key)->count() : null]),
            'visits' => $filter($query(), $tab)->with('member', 'visitors', 'followUp', 'doer')
                ->when(in_array($tab, ['upcoming', 'mine'], true), fn ($q) => $q->orderBy('scheduled_at'), fn ($q) => $q->latest('scheduled_at'))
                ->paginate(20)->withQueryString(),
            'members' => Member::active()->orderBy('name')->get(['id', 'name', 'address', 'phone']),
            'carers' => $this->carers(),
            'purposes' => Visit::PURPOSES,
            'outcomes' => Visit::OUTCOMES,
            'followUp' => $followUp,
            'thisMonth' => Visit::where('status', 'done')->where('done_at', '>=', now()->startOfMonth())->count(),
        ]);
    }

    public function store(Request $request, CareNotices $notices): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['nullable', Rule::exists('members', 'id')],
            'follow_up_id' => ['nullable', Rule::exists('follow_ups', 'id')],
            'name' => ['required_without_all:member_id,follow_up_id', 'nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(array_keys(Visit::PURPOSES))],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'visitors' => ['required', 'array', 'min:1'],
            'visitors.*' => [Rule::in($this->carers()->pluck('id'))],
        ], [
            'name.required_without_all' => 'Choose a member, or type the name of the person you\'re visiting.',
            'visitors.required' => 'Choose who\'s going.',
            'scheduled_at.after' => 'Choose a time in the future.',
        ]);

        $member = ($data['member_id'] ?? null) ? Member::find($data['member_id']) : null;
        $followUp = ($data['follow_up_id'] ?? null) ? FollowUp::find($data['follow_up_id']) : null;
        $member ??= $followUp?->member;
        $visit = Visit::create([
            ...$data,
            'member_id' => $member?->id,
            'first_timer_id' => $followUp?->first_timer_id,
            'name' => $member?->name ?? $followUp?->personName() ?? $data['name'],
            'phone' => $data['phone'] ?? $member?->phone ?? $followUp?->phone(),
            'address' => $data['address'] ?? $member?->address,
            'tell_them' => $request->boolean('tell_them'),
        ]);
        $visit->forceFill(['created_by' => $request->user()->id])->save();
        $visit->visitors()->sync($data['visitors']);
        $visit->load('visitors', 'member.user');

        $notices->tellVisitors($visit, $visit->visitors->reject(fn ($u) => $u->is($request->user())));
        if ($visit->tell_them) {
            $notices->tellPerson($visit);
        }

        return redirect()->route('admin.visits.index')->with('status', "Visit to {$visit->name} planned for ".$visit->scheduled_at->format('D j M, g:i A').'. '.($visit->visitors->count() > 1 || ! $visit->visitors->first()?->is($request->user()) ? 'Everyone going has been emailed.' : ''));
    }

    /**
     * Change when it is or who's going.
     */
    public function update(Request $request, Visit $visit, CareNotices $notices): RedirectResponse
    {
        abort_unless($visit->status === 'planned', 422, 'This visit has already happened or been cancelled.');
        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'visitors' => ['required', 'array', 'min:1'],
            'visitors.*' => [Rule::in($this->carers()->pluck('id'))],
        ], ['visitors.required' => 'Choose who\'s going.']);

        $before = $visit->visitors()->pluck('users.id');
        $moved = ! $visit->scheduled_at->eq($data['scheduled_at']);
        $visit->forceFill(['scheduled_at' => $data['scheduled_at'], 'reminded_at' => $moved ? null : $visit->reminded_at])->save();
        $visit->visitors()->sync($data['visitors']);
        $visit->load('visitors');
        $notices->tellVisitors($visit, $moved ? $visit->visitors : $visit->visitors->whereNotIn('id', $before), $moved ? 'Visit to '.$visit->name.' moved' : null);

        return back()->with('status', 'Visit updated.');
    }

    public function complete(Request $request, Visit $visit): RedirectResponse
    {
        abort_unless($visit->status === 'planned', 422, 'This visit has already been recorded.');
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(Visit::OUTCOMES))],
            'report' => ['nullable', 'string', 'max:2000'],
        ]);
        $visit->complete($request->user(), $data['outcome'], $data['report'] ?? null);

        return back()->with('status', 'Thank you. The visit to '.$visit->name.' is recorded.');
    }

    public function cancel(Visit $visit): RedirectResponse
    {
        abort_unless($visit->status === 'planned', 422);
        $visit->forceFill(['status' => 'cancelled'])->save();

        return back()->with('status', 'Visit to '.$visit->name.' cancelled.');
    }

    /**
     * Staff who can go on visits: those whose role covers follow-up.
     *
     * @return Collection<int, User>
     */
    private function carers(): Collection
    {
        return User::whereNotNull('role')->orderBy('name')->get()->filter(fn (User $u) => $u->canManage('care'))->values();
    }
}

<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\MediaRequest;
use App\Models\Member;
use App\Models\Unit;
use App\Services\Media\MediaDesk;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The media team's side of requests: take them on, talk to whoever asked, and deliver.
 */
class MediaRequestController extends Controller
{
    public const TABS = ['open' => 'To do', 'mine' => 'Mine', 'delivered' => 'Delivered', 'closed' => 'Closed'];

    public function index(Request $request, Unit $unit): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'open';
        $memberId = $request->user()->member?->id;

        return view('unit.media.requests', [
            'unit' => $unit,
            'tab' => $tab,
            'requests' => MediaRequest::with('unit', 'assignee', 'requester.member')
                ->when($tab === 'open', fn ($q) => $q->whereIn('status', MediaRequest::OPEN)->orderBy('needed_by'))
                ->when($tab === 'mine', fn ($q) => $q->whereIn('status', MediaRequest::OPEN)->where('assigned_member_id', $memberId ?? 0)->orderBy('needed_by'))
                ->when($tab === 'delivered', fn ($q) => $q->where('status', 'delivered')->latest('delivered_at'))
                ->when($tab === 'closed', fn ($q) => $q->whereIn('status', ['completed', 'declined', 'cancelled'])->latest('closed_at'))
                ->paginate(20)->withQueryString(),
            'counts' => [
                'open' => MediaRequest::whereIn('status', MediaRequest::OPEN)->count(),
                'mine' => $memberId ? MediaRequest::whereIn('status', MediaRequest::OPEN)->where('assigned_member_id', $memberId)->count() : 0,
            ],
        ]);
    }

    public function show(Request $request, Unit $unit, MediaRequest $mediaRequest): View
    {
        return view('unit.media.request', [
            'unit' => $unit,
            'r' => $mediaRequest->load('unit', 'assignee', 'files.uploader.member', 'requester.member'),
            'comments' => $mediaRequest->comments()->with('author.member')->get(),
            'canLead' => Gate::allows('lead-media', $unit),
            'team' => $unit->members()->active()->orderBy('name')->get(['id', 'name']),
            'canDeliver' => $this->canDeliver($request, $unit, $mediaRequest),
        ]);
    }

    /**
     * Leaders give it to someone; a team member can take an unassigned request for themselves.
     */
    public function assign(Request $request, Unit $unit, MediaRequest $mediaRequest, MediaDesk $desk): RedirectResponse
    {
        $user = $request->user();
        if (Gate::allows('lead-media', $unit) && $request->filled('member_id')) {
            $memberId = $request->validate(['member_id' => ['required', Rule::exists('members', 'id')->where('status', 'active')]])['member_id'];
            $member = Member::findOrFail($memberId);
        } else {
            $member = $user->member ?? abort(422, 'Only media team members with a member record can take requests.');
            abort_if($mediaRequest->assigned_member_id && $mediaRequest->assigned_member_id !== $member->id, 403, 'Someone is already on this one. Ask your HOD to reassign it.');
        }
        $desk->assign($mediaRequest, $member, $user);

        return back()->with('status', $member->id === $user->member?->id ? 'It\'s yours.' : "Given to {$member->name}.");
    }

    public function decline(Request $request, Unit $unit, MediaRequest $mediaRequest, MediaDesk $desk): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:300']], ['reason.required' => 'Say why, so they know what to do next.'])['reason'];
        $desk->decline($mediaRequest, $reason);

        return back()->with('status', 'Declined. They\'ve been told why.');
    }

    public function deliver(Request $request, Unit $unit, MediaRequest $mediaRequest, MediaDesk $desk): RedirectResponse|JsonResponse
    {
        abort_unless($this->canDeliver($request, $unit, $mediaRequest), 403);
        $data = $request->validate([
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => MediaLibrary::fileRules(),
            'links' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:3000'],
        ], ['files.*.max' => 'One of the files is too big.', 'files.*.extensions' => 'That type of file can\'t be uploaded.']);

        $links = collect(preg_split('/\s+/', (string) ($data['links'] ?? '')))->filter()->values();
        validator(['links' => $links->all()], ['links.*' => ['url:http,https', 'max:500']], ['links.*.url' => 'Put each link (https://…) on its own line.'])->validate();

        $desk->deliver($mediaRequest, $request->user(), $request->file('files', []), $links->all(), $data['note'] ?? null);

        if ($request->expectsJson()) {
            session()->flash('status', 'Delivered. They\'ve been emailed.');

            return response()->json(['redirect' => route('unit.requests.show', [$unit, $mediaRequest])]);
        }

        return back()->with('status', 'Delivered. They\'ve been emailed.');
    }

    public function comment(Request $request, Unit $unit, MediaRequest $mediaRequest, MediaDesk $desk): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:3000'], 'internal' => ['nullable', 'boolean']]);
        $internal = $request->boolean('internal');
        $desk->comment($mediaRequest, $request->user(), $data['body'], $internal, fromTeam: true);

        return back()->with('status', $internal ? 'Note added for the team.' : 'Message sent.');
    }

    public function close(Request $request, Unit $unit, MediaRequest $mediaRequest, MediaDesk $desk): RedirectResponse
    {
        $desk->close($mediaRequest);

        return back()->with('status', 'Closed.');
    }

    /**
     * Whoever it's assigned to, or a leader.
     */
    private function canDeliver(Request $request, Unit $unit, MediaRequest $mediaRequest): bool
    {
        return $mediaRequest->isOpen()
            && (Gate::allows('lead-media', $unit) || ($mediaRequest->assigned_member_id && $mediaRequest->assigned_member_id === $request->user()->member?->id));
    }
}

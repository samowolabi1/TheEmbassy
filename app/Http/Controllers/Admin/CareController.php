<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\FollowUpAssigned;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * The care team's list: who to call, who's calling them, and how it went.
 */
class CareController extends Controller
{
    private const TABS = ['mine' => 'My follow-ups', 'unassigned' => 'Not assigned', 'open' => 'All open', 'done' => 'Done'];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'mine';
        $me = $request->user()->id;
        $query = fn () => FollowUp::query();

        return view('admin.care', [
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => ['label' => $label, 'count' => $key === 'done' ? null : $this->filter($query(), $key, $me)->count()]),
            'followUps' => $this->filter($query(), $tab, $me)->with('member.unit', 'member.fellowship', 'firstTimer', 'assignee', 'doer')
                ->when($tab === 'done', fn ($q) => $q->latest('done_at'), fn ($q) => $q->orderBy('due_on')->orderBy('id'))
                ->paginate(20)->withQueryString(),
            'carers' => $this->carers(),
            'members' => Member::active()->orderBy('name')->get(['id', 'name']),
            'outcomes' => FollowUp::OUTCOMES,
            'overdue' => FollowUp::open()->where('due_on', '<', today())->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', Rule::exists('members', 'id')],
            'note' => ['required', 'string', 'max:255'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
            'assigned_to' => ['nullable', Rule::in($this->carers()->pluck('id'))],
        ]);

        $followUp = FollowUp::create([...$data, 'reason' => 'other']);
        $this->notify($followUp, $request->user());

        return back()->with('status', 'Follow-up added for '.$followUp->personName().'.');
    }

    public function assign(Request $request, FollowUp $followUp): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', Rule::in($this->carers()->pluck('id'))]]);
        $followUp->forceFill(['assigned_to' => $data['assigned_to'] ?? null])->save();
        $this->notify($followUp, $request->user());

        return back()->with('status', $followUp->assignee ? $followUp->personName().' assigned to '.$followUp->assignee->name.'.' : 'Unassigned.');
    }

    public function complete(Request $request, FollowUp $followUp): RedirectResponse
    {
        abort_if($followUp->done_at, 422, 'This follow-up is already done.');
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(FollowUp::OUTCOMES))],
            'outcome_note' => ['nullable', 'string', 'max:255'],
        ]);

        $followUp->complete($request->user(), $data['outcome'], $data['outcome_note'] ?? null);

        return back()->with('status', 'Done: '.$followUp->personName().'.');
    }

    /**
     * Staff whose role covers follow-up.
     *
     * @return Collection<int, User>
     */
    private function carers(): Collection
    {
        return User::whereNotNull('role')->orderBy('name')->get()->filter(fn (User $u) => $u->canManage('care'))->values();
    }

    private function filter($query, string $tab, int $me)
    {
        return match ($tab) {
            'mine' => $query->open()->where('assigned_to', $me),
            'unassigned' => $query->open()->whereNull('assigned_to'),
            'done' => $query->whereNotNull('done_at'),
            default => $query->open(),
        };
    }

    private function notify(FollowUp $followUp, User $by): void
    {
        $followUp->load('assignee');
        if (! $followUp->assignee || $followUp->assignee->is($by)) {
            return;
        }
        try {
            Mail::to($followUp->assignee->email)->send(new FollowUpAssigned($followUp));
        } catch (Throwable $e) {
            Log::error('Could not email follow-up assignment', ['follow_up' => $followUp->id, 'error' => $e->getMessage()]);
        }
    }
}

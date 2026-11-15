<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A unit head's view of follow-ups for people in their unit: who the church is trying to reach,
 * so the head can call too and note how it went. Pastoral notes stay with the pastors.
 */
class FollowUpController extends Controller
{
    /** What a unit head can record. Reaching them closes the follow-up; no answer is noted. */
    public const OUTCOMES = ['reached' => 'Spoke with them', 'visited' => 'Visited', 'prayed' => 'Prayed with them', 'no_answer' => 'No answer (keep it open)'];

    public function index(Unit $unit): View
    {
        $ids = $unit->members()->pluck('members.id');

        return view('unit.follow-ups', [
            'unit' => $unit,
            'open' => FollowUp::open()->whereIn('member_id', $ids)->with('member', 'assignee')->orderBy('due_on')->get(),
            'done' => FollowUp::whereNotNull('done_at')->where('done_at', '>=', now()->subDays(30))->whereIn('member_id', $ids)->with('member', 'doer')->latest('done_at')->get(),
            'outcomes' => self::OUTCOMES,
        ]);
    }

    public function update(Request $request, Unit $unit, FollowUp $followUp): RedirectResponse
    {
        abort_unless($followUp->member && $followUp->member->inUnit($unit), 404);
        abort_if($followUp->done_at, 422, 'This follow-up is already done.');
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(self::OUTCOMES))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $by = $request->user();
        $line = self::OUTCOMES[$data['outcome']].' ('.$unit->name.' head)'.(($data['note'] ?? null) ? ': '.$data['note'] : '');

        if ($data['outcome'] === 'no_answer') {
            $followUp->addToLog($by, $line);

            return back()->with('status', 'Noted. It stays open so someone tries again.');
        }
        $followUp->addToLog($by, $line);
        $followUp->complete($by, $data['outcome'], mb_substr($line, 0, 255));

        return back()->with('status', 'Thank you for reaching '.$followUp->member->firstName().'. The care team can see your note.');
    }
}

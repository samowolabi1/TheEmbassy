<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Unit\WelfareController as UnitWelfareController;
use App\Models\Member;
use App\Models\WelfareCase;
use App\Services\Welfare\WelfareDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Members asking the welfare team for help, and case workers logging their visits and calls.
 */
class WelfareController extends Controller
{
    public function index(Request $request): View
    {
        $member = $request->user()->member ?? abort(404);

        return view('account.welfare', [
            'member' => $member,
            'requests' => WelfareCase::where('member_id', $member->id)->where('source', 'member')->latest()->get(),
            'assigned' => WelfareCase::open()->where('assigned_member_id', $member->id)->latest()->get(),
            'categories' => config('welfare.categories'),
        ]);
    }

    public function store(Request $request, WelfareDesk $desk): RedirectResponse
    {
        $member = $request->user()->member ?? abort(404);
        abort_unless($member->status === Member::ACTIVE, 403, 'Welfare requests are for members.');
        $data = $request->validate(UnitWelfareController::requestRules());

        $case = new WelfareCase([...$data, 'member_id' => $member->id, 'amount_requested' => isset($data['amount_requested']) ? (int) round($data['amount_requested'] * 100) : null]);
        $desk->open($case, $request->user(), 'member');

        return back()->with('status', 'Your request has been sent to the welfare team, in confidence. Someone will be in touch.');
    }

    /**
     * A case worker's view of a case assigned to them: who, what's needed, and the log.
     */
    public function show(Request $request, WelfareCase $case): View
    {
        $this->ensureWorker($request, $case);

        return view('account.welfare-case', ['case' => $case->load('member', 'notes.author'), 'kinds' => config('welfare.note_kinds')]);
    }

    public function note(Request $request, WelfareCase $case, WelfareDesk $desk): RedirectResponse
    {
        $this->ensureWorker($request, $case);
        $data = $request->validate(UnitWelfareController::noteRules());
        $desk->log($case, $request->user(), $data['kind'], $data['body'], $data['happened_on']);

        return back()->with('status', 'Added to the case log.');
    }

    private function ensureWorker(Request $request, WelfareCase $case): void
    {
        abort_unless($case->isOpen() && $request->user()->member && $case->assigned_member_id === $request->user()->member->id, 404);
    }
}

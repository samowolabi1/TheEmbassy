<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\User;
use App\Services\Pastoral\Appointments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A member booking a visit with a pastor, and following their visits.
 */
class AppointmentController extends Controller
{
    public function index(Request $request, Appointments $appointments): View
    {
        $member = $this->member($request);
        $pastors = $appointments->pastors();
        $pastor = $pastors->firstWhere('id', (int) $request->query('pastor')) ?? ($pastors->count() === 1 ? $pastors->first() : null);

        return view('account.appointments', [
            'member' => $member,
            'pastors' => $pastors,
            'pastor' => $pastor,
            'times' => $pastor ? $appointments->openTimes($pastor) : [],
            'mine' => Appointment::with('pastor.member')->where('member_id', $member->id)->latest('starts_at')->take(20)->get(),
        ]);
    }

    public function store(Request $request, Appointments $appointments): RedirectResponse
    {
        $member = $this->member($request);
        $data = $request->validate([
            'pastor_id' => ['required', Rule::in($appointments->pastors()->modelKeys())],
            'start' => ['required', 'date_format:Y-m-d H:i'],
            'mode' => ['required', Rule::in(array_keys(Appointment::MODES))],
            'topic' => ['required', Rule::in(array_keys(Appointment::TOPICS))],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['start.required' => 'Choose a time.', 'pastor_id.required' => 'Choose a pastor.']);

        $appointment = $appointments->book($member, User::findOrFail($data['pastor_id']), Carbon::createFromFormat('Y-m-d H:i', $data['start']),
            $data['mode'], $data['topic'], $data['note'] ?? null);

        return redirect()->route('account.appointments')->with('status', 'Your request for '.$appointment->when().' is sent. You\'ll get an email when the pastor confirms it.');
    }

    public function cancel(Request $request, Appointment $appointment, Appointments $appointments): RedirectResponse
    {
        abort_unless($appointment->member_id === $this->member($request)->id, 404);
        $appointments->cancel($appointment);

        return back()->with('status', 'Visit cancelled.');
    }

    private function member(Request $request): Member
    {
        $member = $request->user()->member;
        abort_unless($member && $member->status === Member::ACTIVE, 404);

        return $member;
    }
}

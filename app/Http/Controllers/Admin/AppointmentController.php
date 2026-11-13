<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PastorHour;
use App\Models\PastorTimeOff;
use App\Models\User;
use App\Services\Pastoral\Appointments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Visits with the pastors: requests to confirm, move or decline; the coming schedule; and each
 * pastor's weekly hours and time off. Pastors see their own visits first.
 */
class AppointmentController extends Controller
{
    public const TABS = ['requests' => 'To confirm', 'upcoming' => 'Coming up', 'past' => 'Past', 'closed' => 'Declined & cancelled'];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'requests';
        $user = $request->user();
        $pastors = User::whereIn('role', ['super', 'pastor'])->orderBy('name')->get();
        // Pastors start on their own visits; the office sees everyone's.
        $default = in_array($user->role, ['super', 'pastor'], true) ? (string) $user->id : 'all';
        $pastorFilter = $request->query('pastor', $default);
        $pastorId = ctype_digit((string) $pastorFilter) ? (int) $pastorFilter : null;

        $base = fn () => Appointment::query()->when($pastorId, fn ($q) => $q->where('pastor_id', $pastorId));

        return view('admin.appointments.index', [
            'tab' => $tab,
            'pastors' => $pastors,
            'pastorFilter' => $pastorId ? (string) $pastorId : 'all',
            'appointments' => $base()->with('member', 'pastor.member')
                ->when($tab === 'requests', fn ($q) => $q->where('status', 'requested')->orderBy('starts_at'))
                ->when($tab === 'upcoming', fn ($q) => $q->where('status', 'confirmed')->where('ends_at', '>=', now())->orderBy('starts_at'))
                ->when($tab === 'past', fn ($q) => $q->where(fn ($w) => $w->whereIn('status', ['completed', 'missed'])->orWhere(fn ($c) => $c->where('status', 'confirmed')->where('ends_at', '<', now())))->latest('starts_at'))
                ->when($tab === 'closed', fn ($q) => $q->whereIn('status', ['declined', 'cancelled'])->latest('starts_at'))
                ->paginate(20)->withQueryString(),
            'counts' => [
                'requests' => $base()->where('status', 'requested')->count(),
                'upcoming' => $base()->where('status', 'confirmed')->where('ends_at', '>=', now())->count(),
            ],
        ]);
    }

    public function confirm(Request $request, Appointment $appointment, Appointments $appointments): RedirectResponse
    {
        $data = $request->validate([
            'staff_note' => ['nullable', 'string', 'max:300'],
            'call_link' => ['nullable', 'url:http,https', 'max:500'],
            'starts_at' => ['nullable', 'date'],
        ]);
        $newTime = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;
        $appointments->confirm($appointment, $request->user(), $data['staff_note'] ?? null, $data['call_link'] ?? null, $newTime);

        return back()->with('status', 'Confirmed. '.$appointment->member->name.' has been told.');
    }

    public function decline(Request $request, Appointment $appointment, Appointments $appointments): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:300']], ['reason.required' => 'Say why, kindly; the member sees it.'])['reason'];
        $appointments->decline($appointment, $request->user(), $reason);

        return back()->with('status', 'Declined. '.$appointment->member->name.' has been told.');
    }

    public function finish(Request $request, Appointment $appointment, Appointments $appointments): RedirectResponse
    {
        $appointments->finish($appointment, $request->input('outcome') === 'completed');

        return back()->with('status', 'Marked as '.mb_strtolower($appointment->fresh()->statusLabel()).'.');
    }

    /**
     * The pastors' weekly hours and time off.
     */
    public function hours(Request $request): View
    {
        return view('admin.appointments.hours', [
            'pastors' => User::whereIn('role', ['super', 'pastor'])->orderBy('name')->get(),
            'hours' => PastorHour::with('pastor')->orderBy('user_id')->orderBy('weekday')->orderBy('starts')->get()->groupBy('user_id'),
            'timeOff' => PastorTimeOff::where('ends_on', '>=', today())->orderBy('starts_on')->get()->groupBy('user_id'),
        ]);
    }

    public function storeHour(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereIn('role', ['super', 'pastor'])],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'starts' => ['required', 'date_format:H:i'],
            'ends' => ['required', 'date_format:H:i', 'after:starts'],
            'slot_minutes' => ['required', 'integer', Rule::in([15, 20, 30, 45, 60, 90])],
            'mode' => ['required', Rule::in(array_keys(PastorHour::MODES))],
            'location' => ['nullable', 'string', 'max:150'],
        ], ['user_id.exists' => 'Choose a pastor.', 'ends.after' => 'The end time must be after the start.']);
        PastorHour::create($data);

        return back()->with('status', 'Hours added. Members can book them now.');
    }

    public function destroyHour(PastorHour $hour): RedirectResponse
    {
        $hour->delete();

        return back()->with('status', 'Hours removed. Visits already booked stay booked.');
    }

    public function storeTimeOff(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereIn('role', ['super', 'pastor'])],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:150'],
        ]);
        PastorTimeOff::create($data);
        $booked = Appointment::where('pastor_id', $data['user_id'])->whereIn('status', Appointment::ACTIVE)
            ->whereBetween('starts_at', [Carbon::parse($data['starts_on'])->startOfDay(), Carbon::parse($data['ends_on'])->endOfDay()])->count();

        return back()->with('status', 'Time off saved. No new visits can be booked then.'.($booked ? " {$booked} already booked then: move or decline them on the appointments page." : ''));
    }

    public function destroyTimeOff(PastorTimeOff $timeOff): RedirectResponse
    {
        $timeOff->delete();

        return back()->with('status', 'Time off removed.');
    }
}

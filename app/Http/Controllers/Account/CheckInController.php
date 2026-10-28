<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Attendance\CheckIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Members check themselves in to a service, at the venue or online.
 */
class CheckInController extends Controller
{
    public function show(Request $request): View
    {
        $member = $request->user()->member;
        $services = Service::openForCheckIn();
        $done = $member ? $member->services()->whereIn('services.id', $services->pluck('id'))->pluck('services.id')->all() : [];

        return view('account.check-in', [
            'member' => $member,
            'services' => $services,
            'done' => $done,
            'code' => preg_replace('/\D/', '', (string) $request->query('code')),
            'requireCode' => filter_var(config('church.checkin.require_code'), FILTER_VALIDATE_BOOL),
            'recent' => $member ? $member->services()->orderByDesc('held_on')->take(6)->get() : collect(),
        ]);
    }

    public function store(Request $request, CheckIn $checkIn): RedirectResponse
    {
        $data = $request->validate([
            'service' => ['required', 'integer'],
            'mode' => ['required', Rule::in(['in_person', 'online'])],
            'code' => ['nullable', 'string', 'max:12'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);
        $member = $request->user()->member ?? abort(404);
        $service = Service::findOrFail($data['service']);

        if ($data['mode'] === 'online') {
            $checkIn->online($member, $service, $data['code'] ?? null);
            $message = 'You\'re checked in online. Enjoy the service!';
        } else {
            $checkIn->inPerson($member, $service, $this->float($data['latitude'] ?? null), $this->float($data['longitude'] ?? null), $this->float($data['accuracy'] ?? null), $data['code'] ?? null);
            $message = 'You\'re checked in. Welcome to church!';
        }

        return redirect()->route('check-in')->with('status', $message);
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}

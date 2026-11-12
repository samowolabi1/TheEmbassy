<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Service;
use App\Services\Attendance\CheckIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Members join a service online from their account: the stream plays here, or the meeting opens,
 * and joining this way marks them present online.
 */
class JoinController extends Controller
{
    public function show(Request $request, Service $service): View
    {
        $member = $this->member($request);
        abort_unless($service->online_checkin, 404);

        return view('account.join-service', [
            'service' => $service->load('setup'),
            'member' => $member,
            'open' => $service->checkInIsOpen(),
            'present' => $service->members()->whereKey($member->id)->exists(),
            'youtube' => $service->youtubeId(),
            'meeting' => $service->meetingUrl(),
            'watchMinutes' => max(1, (int) config('church.checkin.watch_minutes', 5)),
        ]);
    }

    /**
     * Opening the meeting through the app counts as joining.
     */
    public function open(Request $request, Service $service, CheckIn $checkIn): RedirectResponse
    {
        $member = $this->member($request);
        abort_unless($service->online_checkin && $service->meetingUrl(), 404);
        $checkIn->joined($member, $service);

        return redirect()->away($service->meetingUrl());
    }

    /**
     * The page reports once a minute while the stream is on screen. Enough minutes in a row
     * (allowing a missed beat for a flaky connection) marks the member present.
     */
    public function watching(Request $request, Service $service, CheckIn $checkIn): JsonResponse
    {
        $member = $this->member($request);
        abort_unless($service->online_checkin, 404);
        $needed = max(1, (int) config('church.checkin.watch_minutes', 5));

        if ($service->members()->whereKey($member->id)->exists()) {
            return response()->json(['present' => true, 'minutes_left' => 0]);
        }
        if (! $service->checkInIsOpen()) {
            return response()->json(['present' => false, 'closed' => true]);
        }

        $key = "watching:{$service->id}:{$member->id}";
        $state = Cache::get($key, ['since' => now()->timestamp, 'last' => 0, 'beats' => 0]);
        // More than three minutes since the last beat means they stopped watching: start again.
        if ($state['last'] && now()->timestamp - $state['last'] > 180) {
            $state = ['since' => now()->timestamp, 'last' => 0, 'beats' => 0];
        }
        // Beats closer than 45 seconds apart don't count twice.
        if (! $state['last'] || now()->timestamp - $state['last'] >= 45) {
            $state['beats']++;
            $state['last'] = now()->timestamp;
        }
        Cache::put($key, $state, now()->addHours(6));

        $watched = intdiv(now()->timestamp - $state['since'], 60);
        if ($watched >= $needed && $state['beats'] >= $needed) {
            $present = $checkIn->joined($member, $service);
            Cache::forget($key);

            return response()->json(['present' => $present, 'minutes_left' => 0]);
        }

        return response()->json(['present' => false, 'minutes_left' => max(1, $needed - min($watched, $state['beats'] - 1))]);
    }

    private function member(Request $request): Member
    {
        $member = $request->user()->member;
        abort_unless($member && $member->status === Member::ACTIVE, 403, 'Joining online is for members.');

        return $member;
    }
}

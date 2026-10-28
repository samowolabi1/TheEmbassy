<?php

namespace App\Services\Attendance;

use App\Models\Member;
use App\Models\Service;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A member checking themselves in to a service.
 *
 * In person: their phone must put them within the venue's radius, with a usable accuracy, and
 * (unless the church switches it off) they enter the code on the check-in screen, which changes
 * every minute. A phone's location can be faked; the code means they can also see the screen.
 *
 * Online: they enter the code shown on the live stream while check-in is open. Stream delay is
 * allowed for. Only the distance is kept, never their coordinates.
 */
class CheckIn
{
    public const ONLINE_CODE_MINUTES = 3;

    public function inPerson(Member $member, Service $service, ?float $lat, ?float $lng, ?float $accuracy, ?string $code): int
    {
        $this->ensureOpen($member, $service, $service->in_person_checkin, 'This service doesn\'t take in-person check-in.');

        [$venueLat, $venueLng] = [config('church.checkin.latitude'), config('church.checkin.longitude')];
        if (! is_numeric($venueLat) || ! is_numeric($venueLng)) {
            $this->fail('The church hasn\'t set its location yet. Please ask an usher to mark you present.');
        }
        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $this->fail('We couldn\'t get your location. Allow location for this site in your browser, then try again.');
        }
        if ($accuracy === null || $accuracy > (float) config('church.checkin.max_accuracy')) {
            $this->fail('Your phone\'s location is too rough right now'.($accuracy ? ' (about '.round($accuracy).' m)' : '').'. Turn on GPS or step near a window, and try again, or ask an usher.');
        }

        $distance = (int) round($this->metresBetween($lat, $lng, (float) $venueLat, (float) $venueLng));
        if ($distance > (int) config('church.checkin.radius')) {
            $this->fail('You seem to be about '.$this->friendly($distance).' from church. Check-in works once you\'re at the venue.');
        }

        if (filter_var(config('church.checkin.require_code'), FILTER_VALIDATE_BOOL) && ! $service->acceptsCode($code)) {
            $this->fail('That code doesn\'t match the one on the screen. It changes every minute; enter the one showing now.', 'code');
        }

        $this->record($member, $service, 'in_person', $distance);

        return $distance;
    }

    public function online(Member $member, Service $service, ?string $code): void
    {
        $this->ensureOpen($member, $service, $service->online_checkin, 'This service doesn\'t take online check-in.');

        if (! $service->acceptsCode($code, self::ONLINE_CODE_MINUTES)) {
            $this->fail('That code doesn\'t match the one on the live stream. Enter the one showing now.', 'code');
        }

        $this->record($member, $service, 'online', null);
    }

    /**
     * The great-circle distance between two points, in metres.
     */
    public function metresBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rad = M_PI / 180;
        $dLat = ($lat2 - $lat1) * $rad;
        $dLng = ($lng2 - $lng1) * $rad;
        $a = sin($dLat / 2) ** 2 + cos($lat1 * $rad) * cos($lat2 * $rad) * sin($dLng / 2) ** 2;

        return 6_371_000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function ensureOpen(Member $member, Service $service, bool $modeAllowed, string $notAllowed): void
    {
        if ($member->status !== Member::ACTIVE) {
            $this->fail('Check-in is for members. Once the church office confirms your membership, you can check in here.');
        }
        if (! $modeAllowed) {
            $this->fail($notAllowed);
        }
        if (! $service->checkInIsOpen()) {
            $this->fail('Check-in for this service isn\'t open right now.');
        }
        if ($service->members()->whereKey($member->id)->exists()) {
            $this->fail('You\'re already checked in to this service. Welcome!');
        }
    }

    private function record(Member $member, Service $service, string $mode, ?int $distance): void
    {
        DB::transaction(function () use ($member, $service, $mode, $distance) {
            try {
                $service->members()->attach($member->id, ['mode' => $mode, 'checked_in_at' => now(), 'distance_m' => $distance]);
            } catch (UniqueConstraintViolationException) {
                $this->fail('You\'re already checked in to this service. Welcome!'); // a double tap
            }
            if (! $member->last_attended_on || $member->last_attended_on->lt($service->held_on)) {
                $member->forceFill(['last_attended_on' => $service->held_on])->save();
            }
        });
    }

    private function friendly(int $metres): string
    {
        return $metres >= 1000 ? round($metres / 1000, 1).' km' : $metres.' m';
    }

    private function fail(string $message, string $field = 'location'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

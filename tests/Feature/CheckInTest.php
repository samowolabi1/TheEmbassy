<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use App\Services\Attendance\CheckIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    // The venue, and points about 50 m and 2 km away.
    private const VENUE = [6.4281, 3.4219];

    private const NEARBY = [6.42855, 3.4219];

    private const FAR = [6.4461, 3.4219];

    protected function setUp(): void
    {
        parent::setUp();

        config(['church.checkin' => [
            'latitude' => self::VENUE[0], 'longitude' => self::VENUE[1], 'radius' => 150, 'max_accuracy' => 200,
            'require_code' => true, 'opens_before' => 30, 'closes_after' => 30,
        ]]);
    }

    private function service(array $overrides = []): Service
    {
        return Service::create([
            'held_on' => today(), 'kind' => 'sunday', 'starts_at' => now()->subMinutes(10), 'ends_at' => now()->addHour(),
            'in_person_checkin' => true, 'online_checkin' => true, ...$overrides,
        ]);
    }

    private function checkIn(Service $service, array $data)
    {
        return $this->post(route('check-in.store'), ['service' => $service->id, 'mode' => 'in_person', ...$data]);
    }

    public function test_a_member_at_church_checks_in_with_the_screen_code(): void
    {
        $member = Member::factory()->active()->create();
        $service = $this->service();

        $this->actingAs($member->user)->get(route('check-in'))->assertOk()->assertSee("I'm at church", false)->assertSee("I'm watching online", false);
        $this->checkIn($service, ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 20, 'code' => $service->code()])
            ->assertRedirect(route('check-in'))->assertSessionHas('status', "You're checked in. Welcome to church!");

        $pivot = $service->members()->first()->pivot;
        $this->assertSame('in_person', $pivot->mode);
        $this->assertEqualsWithDelta(50, $pivot->distance_m, 5);
        $this->assertTrue($member->fresh()->last_attended_on->isToday());
        $this->assertDatabaseMissing('member_service', ['distance_m' => null, 'mode' => 'in_person']);
        $this->get(route('check-in'))->assertSee("You're checked in", false);
    }

    public function test_members_far_from_church_cannot_check_in_in_person(): void
    {
        $member = Member::factory()->active()->create();
        $service = $this->service();

        $this->actingAs($member->user);
        $this->checkIn($service, ['latitude' => self::FAR[0], 'longitude' => self::FAR[1], 'accuracy' => 20, 'code' => $service->code()])
            ->assertSessionHasErrors(['location' => 'You seem to be about 2 km from church. Check-in works once you\'re at the venue.']);
        $this->assertSame(0, $service->members()->count());
    }

    public function test_the_admin_set_radius_decides_how_far_counts(): void
    {
        config(['church.checkin.radius' => 3000]);
        $member = Member::factory()->active()->create();
        $service = $this->service();

        $this->actingAs($member->user);
        $this->checkIn($service, ['latitude' => self::FAR[0], 'longitude' => self::FAR[1], 'accuracy' => 20, 'code' => $service->code()])->assertSessionHasNoErrors();
    }

    public function test_a_wrong_or_old_code_is_refused_even_at_church(): void
    {
        $member = Member::factory()->active()->create();
        $service = $this->service();
        $old = $service->code(now()->subMinutes(5));

        $this->actingAs($member->user);
        $this->checkIn($service, ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 20, 'code' => $old])->assertSessionHasErrors('code');
        $this->checkIn($service, ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 20])->assertSessionHasErrors('code');

        // Last minute's code still works, for someone typing as it changes.
        $this->checkIn($service, ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 20, 'code' => $service->code(now()->subMinute())])->assertSessionHasNoErrors();
    }

    public function test_the_church_can_switch_off_the_code_for_in_person(): void
    {
        config(['church.checkin.require_code' => false]);
        $member = Member::factory()->active()->create();
        $service = $this->service();

        $this->actingAs($member->user);
        $this->checkIn($service, ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 20])->assertSessionHasNoErrors();
    }

    public function test_rough_or_missing_locations_are_refused(): void
    {
        $member = Member::factory()->active()->create();
        $service = $this->service();
        $this->actingAs($member->user);

        $this->checkIn($service, ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 900, 'code' => $service->code()])->assertSessionHasErrors('location');
        $this->checkIn($service, ['code' => $service->code()])->assertSessionHasErrors('location');
        $this->assertSame(0, $service->members()->count());
    }

    public function test_check_in_only_opens_around_the_service_time(): void
    {
        $member = Member::factory()->active()->create();
        $later = $this->service(['starts_at' => now()->addHours(3), 'ends_at' => now()->addHours(5)]);

        $this->actingAs($member->user)->get(route('check-in'))->assertSee('Check-in opens 30 minutes before each service');
        $this->checkIn($later, ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 20, 'code' => $later->code()])
            ->assertSessionHasErrors(['location' => "Check-in for this service isn't open right now."]);

        $this->travel(2)->hours();
        $this->travel(35)->minutes();
        $this->checkIn($later, ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 20, 'code' => $later->code()])->assertSessionHasNoErrors();
    }

    public function test_members_check_in_once_per_service(): void
    {
        $member = Member::factory()->active()->create();
        $service = $this->service();
        $this->actingAs($member->user);
        $data = ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 20, 'code' => $service->code()];

        $this->checkIn($service, $data)->assertSessionHasNoErrors();
        $this->checkIn($service, $data)->assertSessionHasErrors('location');
        $this->post(route('check-in.store'), ['service' => $service->id, 'mode' => 'online', 'code' => $service->code()])->assertSessionHasErrors('location');
        $this->assertSame(1, $service->members()->count());
    }

    public function test_online_check_in_needs_the_stream_code_and_allows_for_stream_delay(): void
    {
        $member = Member::factory()->active()->create();
        $service = $this->service(['in_person_checkin' => false]);
        $this->actingAs($member->user);

        $this->get(route('check-in'))->assertDontSee("I'm at church", false)->assertSee("I'm watching online", false);
        $this->post(route('check-in.store'), ['service' => $service->id, 'mode' => 'online', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('check-in.store'), ['service' => $service->id, 'mode' => 'in_person', 'latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 10, 'code' => $service->code()])
            ->assertSessionHasErrors('location');

        $delayed = $service->code(now()->subMinutes(CheckIn::ONLINE_CODE_MINUTES));
        $this->post(route('check-in.store'), ['service' => $service->id, 'mode' => 'online', 'code' => $delayed])->assertSessionHasNoErrors();
        $this->assertSame('online', $service->members()->first()->pivot->mode);
        $this->assertNull($service->members()->first()->pivot->distance_m);
    }

    public function test_pending_members_and_guests_cannot_check_in(): void
    {
        $service = $this->service();
        $pending = Member::factory()->create();

        $this->post(route('check-in.store'), ['service' => $service->id, 'mode' => 'online'])->assertRedirect(route('login'));
        $this->actingAs($pending->user)->post(route('check-in.store'), ['service' => $service->id, 'mode' => 'online', 'code' => $service->code()])->assertSessionHasErrors('location');
        $this->assertSame(0, $service->members()->count());
    }

    public function test_in_person_check_in_waits_for_the_church_location(): void
    {
        config(['church.checkin.latitude' => null]);
        $member = Member::factory()->active()->create();
        $service = $this->service();

        $this->actingAs($member->user);
        $this->checkIn($service, ['latitude' => self::NEARBY[0], 'longitude' => self::NEARBY[1], 'accuracy' => 20, 'code' => $service->code()])
            ->assertSessionHasErrors(['location' => "The church hasn't set its location yet. Please ask an usher to mark you present."]);
    }

    public function test_codes_differ_between_services_and_minutes(): void
    {
        $a = $this->service();
        $b = $this->service(['kind' => 'special']);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $a->code());
        $this->assertNotSame($a->code(), $b->code());
        $this->assertNotSame($a->code(), $a->code(now()->addMinutes(2)));
    }

    public function test_distance_calculation(): void
    {
        $metres = app(CheckIn::class)->metresBetween(6.4281, 3.4219, 6.4461, 3.4219);

        $this->assertEqualsWithDelta(2001, $metres, 5);
    }

    public function test_ushers_schedule_weekly_services_and_open_the_code_screen(): void
    {
        $this->actingAs(User::factory()->staff('usher')->create());
        $sunday = now()->next('Sunday');

        $this->post(route('admin.attendance.store'), [
            'held_on' => $sunday->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00', 'kind' => 'sunday',
            'in_person_checkin' => '1', 'online_checkin' => '1', 'repeat' => 4,
        ])->assertRedirect()->assertSessionHas('status', '5 services scheduled.');

        $this->assertSame(5, Service::count());
        $last = Service::orderByDesc('starts_at')->first();
        $this->assertTrue($last->starts_at->equalTo($sunday->copy()->setTime(9, 0)->addWeeks(4)));

        $first = Service::orderBy('starts_at')->first();
        $this->travelTo($first->starts_at->copy()->subMinutes(5));
        $this->get(route('admin.attendance.screen', $first))->assertOk()->assertSee(substr($first->code(), 0, 3))->assertSee('<svg', false);
        $this->get(route('admin.attendance.index'))->assertSee('Check-in open');
    }

    public function test_check_in_needs_a_start_time(): void
    {
        $this->actingAs(User::factory()->staff('usher')->create())->post(route('admin.attendance.store'), [
            'held_on' => today()->toDateString(), 'kind' => 'midweek', 'online_checkin' => '1',
        ])->assertSessionHasErrors('start_time');
    }

    public function test_settings_validate_the_venue_location(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.settings.update'), ['church__checkin__latitude' => '200', 'church__checkin__radius' => '5'])
            ->assertSessionHasErrors(['church__checkin__latitude', 'church__checkin__radius']);
        $this->put(route('admin.settings.update'), ['church__checkin__latitude' => '6.4281', 'church__checkin__longitude' => '3.4219', 'church__checkin__radius' => '200'])
            ->assertSessionHasNoErrors();
    }
}

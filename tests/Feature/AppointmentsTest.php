<?php

namespace Tests\Feature;

use App\Mail\AppointmentUpdate;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\PastorHour;
use App\Models\User;
use App\Services\Pastoral\Appointments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $pastor;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00')); // a Monday
        $this->pastor = User::factory()->create(['role' => 'pastor', 'name' => 'Pastor Tunde']);
        // Tuesdays 10:00 to 12:00, 30-minute visits.
        PastorHour::create(['user_id' => $this->pastor->id, 'weekday' => 2, 'starts' => '10:00', 'ends' => '12:00', 'slot_minutes' => 30, 'mode' => 'both', 'location' => 'Pastor\'s office']);
        $this->member = Member::factory()->active()->create(['name' => 'Ada Obi']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_open_times_come_from_the_hours_less_bookings_time_off_and_notice(): void
    {
        $times = app(Appointments::class)->openTimes($this->pastor);
        $this->assertSame(['2026-11-17', '2026-11-24', '2026-12-01'], array_keys($times));
        $this->assertSame(['10:00', '10:30', '11:00', '11:30'], array_map(fn ($s) => $s['start']->format('H:i'), $times['2026-11-17']));

        // Less than 12 hours' notice: tomorrow's early slots close as the clock moves on.
        Carbon::setTestNow(Carbon::parse('2026-11-16 22:15'));
        $this->assertSame(['10:30', '11:00', '11:30'], array_map(fn ($s) => $s['start']->format('H:i'), app(Appointments::class)->openTimes($this->pastor)['2026-11-17']));
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00'));

        // Time off takes the whole day away.
        $this->actingAs($this->pastor)->post(route('admin.appointments.time-off.store'), ['user_id' => $this->pastor->id, 'starts_on' => '2026-11-24', 'ends_on' => '2026-11-24'])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('2026-11-24', app(Appointments::class)->openTimes($this->pastor));
    }

    public function test_a_member_books_and_the_pastor_confirms_with_a_new_time(): void
    {
        $this->actingAs($this->member->user)->get(route('account.appointments'))->assertOk()->assertSee('Open times with Pastor Tunde')->assertSee('10:30 AM');
        $this->post(route('account.appointments.store'), ['pastor_id' => $this->pastor->id, 'start' => '2026-11-17 10:30', 'mode' => 'in_person', 'topic' => 'counselling', 'note' => 'Struggling at work'])
            ->assertSessionHasNoErrors();
        $a = Appointment::sole();
        $this->assertSame(['requested', '2026-11-17 11:00'], [$a->status, $a->ends_at->format('Y-m-d H:i')]);
        Mail::assertSent(AppointmentUpdate::class, fn ($m) => $m->hasTo($this->pastor->email) && $m->forStaff);

        // The time is taken now, for everyone.
        $other = Member::factory()->active()->create();
        $this->actingAs($other->user)->post(route('account.appointments.store'), ['pastor_id' => $this->pastor->id, 'start' => '2026-11-17 10:30', 'mode' => 'in_person', 'topic' => 'prayer'])
            ->assertSessionHasErrors('start');
        $this->assertArrayNotHasKey(1, array_filter(app(Appointments::class)->openTimes($this->pastor)['2026-11-17'], fn ($s) => $s['start']->format('H:i') === '10:30'));

        // The pastor sees the private note; the office doesn't.
        $this->actingAs($this->pastor)->get(route('admin.appointments.index'))->assertOk()->assertSee('Ada Obi')->assertSee('Struggling at work');
        $office = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($office)->get(route('admin.appointments.index'))->assertOk()->assertSee('Ada Obi')->assertDontSee('Struggling at work')->assertSee('private note');

        // Moving it onto another booking is refused; a free time is fine.
        Appointment::create(['member_id' => $other->id, 'pastor_id' => $this->pastor->id, 'starts_at' => '2026-11-17 11:30', 'ends_at' => '2026-11-17 12:00', 'mode' => 'call', 'topic' => 'prayer']);
        $this->actingAs($this->pastor)->post(route('admin.appointments.confirm', $a), ['starts_at' => '2026-11-17T11:30'])->assertSessionHasErrors('starts_at');
        $this->post(route('admin.appointments.confirm', $a), ['starts_at' => '2026-11-18T15:00', 'staff_note' => 'Come to the office behind the hall.'])->assertSessionHasNoErrors();
        $a->refresh();
        $this->assertSame(['confirmed', '2026-11-18 15:00', '2026-11-18 15:30'], [$a->status, $a->starts_at->format('Y-m-d H:i'), $a->ends_at->format('Y-m-d H:i')]);
        Mail::assertSent(AppointmentUpdate::class, fn ($m) => $m->hasTo($this->member->user->email) && str_contains($m->body, 'new time'));
        $this->actingAs($this->member->user)->get(route('account.appointments'))->assertSee('Come to the office behind the hall.');

        // The reminder the day before, once.
        Carbon::setTestNow(Carbon::parse('2026-11-17 17:00'));
        $this->artisan('appointments:remind')->expectsOutput('Reminded 1 members of tomorrow\'s visits.');
        $this->artisan('appointments:remind')->expectsOutput('Reminded 0 members of tomorrow\'s visits.');

        // Afterwards, the pastor records that it happened.
        Carbon::setTestNow(Carbon::parse('2026-11-18 16:00'));
        $this->actingAs($this->pastor)->post(route('admin.appointments.finish', $a), ['outcome' => 'completed']);
        $this->assertSame('completed', $a->fresh()->status);
    }

    public function test_members_cancel_have_a_limit_and_declines_are_explained(): void
    {
        $book = fn (string $start) => $this->actingAs($this->member->user)->post(route('account.appointments.store'), ['pastor_id' => $this->pastor->id, 'start' => $start, 'mode' => 'call', 'topic' => 'prayer']);
        $book('2026-11-17 10:00')->assertSessionHasNoErrors();
        $book('2026-11-17 11:00')->assertSessionHasNoErrors();
        $book('2026-11-24 10:00')->assertSessionHasErrors('start'); // two at a time

        [$first, $second] = Appointment::orderBy('id')->get()->all();
        $this->post(route('account.appointments.cancel', $first))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $first->fresh()->status);
        Mail::assertSent(AppointmentUpdate::class, fn ($m) => $m->hasTo($this->pastor->email) && str_contains($m->body, 'cancelled'));

        $this->actingAs($this->pastor)->post(route('admin.appointments.decline', $second), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.appointments.decline', $second), ['reason' => 'I\'m travelling that week.']);
        $this->assertSame('declined', $second->fresh()->status);
        Mail::assertSent(AppointmentUpdate::class, fn ($m) => $m->hasTo($this->member->user->email) && str_contains($m->body, 'travelling'));

        // Someone else's visit can't be cancelled; members who aren't approved can't book.
        $stranger = Member::factory()->active()->create();
        $this->actingAs($stranger->user)->post(route('account.appointments.cancel', $second))->assertNotFound();
        $pending = Member::factory()->create(['status' => Member::PENDING]);
        $this->actingAs($pending->user)->get(route('account.appointments'))->assertNotFound();
        // Staff without the area can't open the admin page.
        $this->actingAs(User::factory()->create(['role' => 'finance']))->get(route('admin.appointments.index'))->assertForbidden();
    }

    public function test_hours_are_set_for_pastors_only(): void
    {
        $office = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($office)->get(route('admin.appointments.hours'))->assertOk()->assertSee('Pastor Tunde');
        $this->post(route('admin.appointments.hours.store'), ['user_id' => $office->id, 'weekday' => 3, 'starts' => '10:00', 'ends' => '11:00', 'slot_minutes' => 30, 'mode' => 'call'])
            ->assertSessionHasErrors('user_id');
        $this->post(route('admin.appointments.hours.store'), ['user_id' => $this->pastor->id, 'weekday' => 3, 'starts' => '12:00', 'ends' => '11:00', 'slot_minutes' => 30, 'mode' => 'call'])
            ->assertSessionHasErrors('ends');
        $this->post(route('admin.appointments.hours.store'), ['user_id' => $this->pastor->id, 'weekday' => 3, 'starts' => '16:00', 'ends' => '17:00', 'slot_minutes' => 60, 'mode' => 'call'])
            ->assertSessionHasNoErrors();
        $wednesday = app(Appointments::class)->openTimes($this->pastor)['2026-11-18'];
        $this->assertSame([['16:00'], ['call']], [array_map(fn ($s) => $s['start']->format('H:i'), $wednesday), $wednesday[0]['modes']]);
        // That time is calls only.
        $this->actingAs($this->member->user)->post(route('account.appointments.store'), ['pastor_id' => $this->pastor->id, 'start' => '2026-11-18 16:00', 'mode' => 'in_person', 'topic' => 'prayer'])
            ->assertSessionHasErrors('mode');
    }
}

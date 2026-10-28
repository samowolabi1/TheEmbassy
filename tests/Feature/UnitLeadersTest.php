<?php

namespace Tests\Feature;

use App\Mail\DutyReminder;
use App\Mail\MeetingScheduled;
use App\Mail\UnitMessageMail;
use App\Models\Member;
use App\Models\Service;
use App\Models\Unit;
use App\Models\UnitMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UnitLeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A choir with a HOD and two members.
     *
     * @return array{0: Unit, 1: Member, 2: Member, 3: Member}
     */
    private function choir(): array
    {
        $unit = Unit::factory()->create(['name' => 'Choir']);
        $hod = Member::factory()->active()->create(['name' => 'Bro. Femi', 'unit_id' => $unit->id]);
        $unit->update(['hod_member_id' => $hod->id]);

        return [$unit, $hod, Member::factory()->active()->create(['name' => 'Ada Obi', 'unit_id' => $unit->id]), Member::factory()->active()->create(['name' => 'Jide Kolawole', 'unit_id' => $unit->id])];
    }

    public function test_admin_sets_a_hod_who_can_open_only_their_unit(): void
    {
        $unit = Unit::factory()->create(['name' => 'Choir']);
        $other = Unit::factory()->create();
        $femi = Member::factory()->active()->create(['name' => 'Bro. Femi']);

        $this->actingAs(User::factory()->staff('secretary')->create())
            ->put(route('admin.units.update', $unit), ['name' => 'Choir', 'hod_member_id' => $femi->id])->assertRedirect();
        $this->assertSame($femi->id, $unit->fresh()->hod_member_id);
        $this->assertSame('Bro. Femi', $unit->fresh()->leader_name);

        $this->actingAs($femi->user);
        $this->get(route('account'))->assertSee('Lead Choir');
        $this->get(route('unit.show', $unit))->assertOk()->assertSee('Head of department');
        $this->get(route('unit.show', $other))->assertForbidden();
    }

    public function test_ordinary_members_cannot_open_the_unit_area(): void
    {
        [$unit, , $ada] = $this->choir();

        $this->actingAs($ada->user)->get(route('unit.show', $unit))->assertForbidden();
        $this->get(route('unit.show', $unit))->assertForbidden();
        auth()->logout();
        $this->get(route('unit.show', $unit))->assertRedirect(route('login'));
    }

    public function test_assistant_must_differ_from_the_hod(): void
    {
        $unit = Unit::factory()->create();
        $femi = Member::factory()->active()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.units.update', $unit), ['name' => $unit->name, 'hod_member_id' => $femi->id, 'assistant_member_id' => $femi->id])
            ->assertSessionHasErrors('assistant_member_id', null, 'unit');
    }

    public function test_hod_adds_and_removes_members_but_not_themselves(): void
    {
        [$unit, $hod, $ada] = $this->choir();
        $tolu = Member::factory()->active()->create(['name' => 'Tolu']);
        $this->actingAs($hod->user);

        $this->get(route('unit.members', $unit))->assertSee('Ada Obi')->assertSee('HOD');
        $this->post(route('unit.members.add', $unit), ['member_id' => $tolu->id])->assertRedirect();
        $this->assertSame($unit->id, $tolu->fresh()->unit_id);

        $this->delete(route('unit.members.remove', [$unit, $ada]))->assertRedirect();
        $this->assertNull($ada->fresh()->unit_id);
        $this->delete(route('unit.members.remove', [$unit, $hod]))->assertStatus(422);
    }

    public function test_meetings_notify_members_and_take_attendance_by_code(): void
    {
        Mail::fake();
        [$unit, $hod, $ada, $jide] = $this->choir();
        $this->actingAs($hod->user);

        $this->post(route('unit.meetings.store', $unit), [
            'title' => 'Rehearsal', 'starts_at' => now()->addMinutes(10)->format('Y-m-d\TH:i'), 'location' => 'Hall B', 'agenda' => 'New songs', 'notify' => '1',
        ])->assertRedirect();
        $meeting = UnitMeeting::sole();
        Mail::assertSent(MeetingScheduled::class, 3);

        $this->get(route('unit.meetings.screen', [$unit, $meeting]))->assertOk()->assertSee(substr($meeting->code(), 0, 3));

        $this->actingAs($ada->user)->get(route('account'))->assertSee('Rehearsal')->assertSee('Check in');
        $this->post(route('meetings.check-in', $meeting), ['code' => '000000'])->assertSessionHasErrors('meeting');
        $this->post(route('meetings.check-in', $meeting), ['code' => $meeting->code()])->assertSessionHas('status');
        $this->assertSame('code', $meeting->attendees()->first()->pivot->mode);

        // Someone from another unit can't check in.
        $outsider = Member::factory()->active()->create();
        $this->actingAs($outsider->user)->post(route('meetings.check-in', $meeting), ['code' => $meeting->code()])->assertForbidden();

        // The HOD ticks Jide, keeps Ada, and writes minutes.
        $this->actingAs($hod->user)->post(route('unit.meetings.mark', [$unit, $meeting]), ['present' => [$ada->id, $jide->id]]);
        $this->assertSame(2, $meeting->attendees()->count());
        $this->assertSame('code', $meeting->attendees()->whereKey($ada->id)->first()->pivot->mode);
        $this->put(route('unit.meetings.update', [$unit, $meeting]), ['title' => 'Rehearsal', 'starts_at' => $meeting->starts_at->format('Y-m-d\TH:i'), 'minutes' => 'Learn two songs by Friday'])->assertRedirect();
        $this->assertSame('Learn two songs by Friday', $meeting->fresh()->minutes);
    }

    public function test_meeting_check_in_only_opens_near_the_time(): void
    {
        [$unit, $hod, $ada] = $this->choir();
        $meeting = $unit->meetings()->create(['title' => 'Planning', 'starts_at' => now()->addDays(2)]);

        $this->actingAs($ada->user)->post(route('meetings.check-in', $meeting), ['code' => $meeting->code()])->assertSessionHasErrors('meeting');
    }

    public function test_online_meetings_need_an_https_link(): void
    {
        [$unit, $hod] = $this->choir();

        $this->actingAs($hod->user)->post(route('unit.meetings.store', $unit), ['title' => 'Call', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'online_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('online_url');
    }

    public function test_unit_messages_are_emailed_and_shown_on_accounts(): void
    {
        Mail::fake();
        [$unit, $hod, $ada] = $this->choir();
        $unit->meetings()->create(['title' => 'Rehearsal', 'starts_at' => now()->addDay()]);

        $this->actingAs($hod->user)->post(route('unit.messages.send', $unit), ['subject' => 'White and gold on Sunday', 'body' => 'Please wear white and gold.'])
            ->assertSessionHas('status');
        Mail::assertSent(UnitMessageMail::class, 3);

        $this->actingAs($ada->user)->get(route('account'))->assertSee('White and gold on Sunday');
    }

    public function test_rota_assigns_unit_members_and_reminds_them_once(): void
    {
        Mail::fake();
        [$unit, $hod, $ada] = $this->choir();
        $service = Service::create(['held_on' => today()->addDay(), 'starts_at' => today()->addDay()->setTime(9, 0), 'kind' => 'sunday']);
        $outsider = Member::factory()->active()->create();
        $this->actingAs($hod->user);

        $this->post(route('unit.rota.store', $unit), ['service_id' => $service->id, 'member_id' => $ada->id, 'role' => 'Lead vocals'])->assertRedirect();
        $this->post(route('unit.rota.store', $unit), ['service_id' => $service->id, 'member_id' => $ada->id, 'role' => 'Alto'])->assertSessionHasErrors('member_id');
        $this->post(route('unit.rota.store', $unit), ['service_id' => $service->id, 'member_id' => $outsider->id, 'role' => 'Alto'])->assertSessionHasErrors('member_id');
        $this->get(route('unit.rota', $unit))->assertSee('Lead vocals');

        $this->artisan('units:remind')->expectsOutput('Sent 1 rota reminders.');
        $this->artisan('units:remind')->expectsOutput('Sent 0 rota reminders.');
        Mail::assertSent(DutyReminder::class, fn ($mail) => $mail->hasTo($ada->email));
        $this->actingAs($ada->user)->get(route('account'))->assertSee('Your duties')->assertSee('Lead vocals');
    }

    public function test_attendance_shows_sundays_and_meetings_per_member(): void
    {
        [$unit, $hod, $ada, $jide] = $this->choir();
        $sunday = Service::create(['held_on' => now()->previous('Sunday'), 'kind' => 'sunday']);
        $sunday->members()->attach($ada->id);
        $meeting = $unit->meetings()->create(['title' => 'Rehearsal', 'starts_at' => now()->subDay()]);
        $meeting->attendees()->attach([$ada->id, $jide->id]);

        $this->actingAs($hod->user)->get(route('unit.attendance', $unit))->assertOk()->assertSee('Ada Obi')->assertSee('100%')->assertSee('50%');
        $this->get(route('unit.show', $unit))->assertOk()->assertSee('33%');
    }

    public function test_staff_who_manage_units_can_open_any_unit(): void
    {
        [$unit] = $this->choir();

        $this->actingAs(User::factory()->staff('secretary')->create())->get(route('unit.show', $unit))->assertOk();
        $this->actingAs(User::factory()->staff('finance')->create())->get(route('unit.show', $unit))->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Mail\CareListMail;
use App\Mail\ReminderMail;
use App\Models\FirstTimer;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\ReminderLog;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FollowUpVisitationTest extends TestCase
{
    use RefreshDatabase;

    private Unit $choir;

    private Member $hod;

    private Member $away;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->choir = Unit::factory()->create(['name' => 'Choir']);
        $this->hod = Member::factory()->active()->create(['name' => 'Samuel Ojo', 'unit_id' => $this->choir->id, 'joined_on' => today()->subYears(2), 'last_attended_on' => today()->subDays(3)]);
        $this->choir->update(['hod_member_id' => $this->hod->id]);
        $this->away = Member::factory()->active()->create(['name' => 'Ada Obi', 'unit_id' => $this->choir->id, 'joined_on' => today()->subYear(), 'last_attended_on' => today()->subMonths(2)]);
    }

    /**
     * Two Sundays with attendance taken; the HOD was at both.
     */
    private function twoSundays(): void
    {
        foreach ([1, 0] as $weeksAgo) {
            Service::create(['held_on' => now()->previous('Sunday')->subWeeks($weeksAgo), 'kind' => 'sunday'])->members()->attach($this->hod->id);
        }
    }

    public function test_two_missed_services_raise_a_follow_up_tell_the_member_and_their_unit_head(): void
    {
        $this->twoSundays();
        $this->artisan('care:scan')->expectsOutput('Sent "we missed you" to 1 members.')->assertSuccessful();

        $this->assertSame(1, FollowUp::where('member_id', $this->away->id)->where('reason', 'absent')->count());
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($this->away->user->email) && $m->headline === 'We missed you at church' && str_contains($m->body, 'we missed you'));
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($this->hod->user->email) && str_contains($m->headline, 'Ada Obi hasn\'t been in church'));
        $this->assertSame(1, ReminderLog::where('kind', 'missed')->count());

        // Once per absence.
        $this->artisan('care:scan')->expectsOutput('Sent "we missed you" to 0 members.');
        // Switched off on Reminders & greetings, nobody gets it.
        $this->away->forceFill(['last_attended_on' => today(), 'missed_message_at' => null])->save();
        config(['church.reminders.missed.enabled' => '0']);
        $this->away->forceFill(['last_attended_on' => today()->subMonths(2)])->save();
        $this->artisan('care:scan')->expectsOutput('Sent "we missed you" to 0 members.');
    }

    public function test_unit_heads_see_and_record_follow_ups_for_their_members(): void
    {
        $this->twoSundays();
        $this->artisan('care:scan');
        $private = FollowUp::create(['member_id' => $this->away->id, 'reason' => 'other', 'note' => 'Lost her job', 'due_on' => today()]);
        $outsider = FollowUp::create(['member_id' => Member::factory()->active()->create()->id, 'reason' => 'other', 'note' => 'Not in choir', 'due_on' => today()]);
        $absent = FollowUp::where('reason', 'absent')->sole();

        $this->actingAs($this->hod->user)->get(route('unit.follow-ups', $this->choir))->assertOk()
            ->assertSee('Ada Obi')->assertSee('Not seen lately')->assertSee('What to say')
            ->assertDontSee('Lost her job')->assertDontSee('Not in choir');

        // No answer keeps it open, with a note the care team sees.
        $this->post(route('unit.follow-ups.update', [$this->choir, $absent]), ['outcome' => 'no_answer', 'note' => 'Phone off'])->assertSessionHas('status');
        $this->assertNull($absent->fresh()->done_at);
        $this->assertStringContainsString('Samuel Ojo: No answer (keep it open) (Choir head): Phone off', $absent->fresh()->log);
        $this->post(route('unit.follow-ups.update', [$this->choir, $absent]), ['outcome' => 'reached', 'note' => 'She was ill, recovering'])->assertSessionHas('status');
        $this->assertSame('reached', $absent->fresh()->outcome);

        $this->post(route('unit.follow-ups.update', [$this->choir, $outsider]), ['outcome' => 'reached'])->assertNotFound();
        $this->actingAs($this->away->user)->get(route('unit.follow-ups', $this->choir))->assertForbidden();

        // The care team sees the head's notes.
        $this->actingAs(User::factory()->create(['role' => 'pastor']))->get(route('admin.care.index', ['tab' => 'done']))->assertOk()->assertSee('Phone off');
        $this->assertNull($private->fresh()->done_at);
    }

    public function test_a_second_visit_is_its_own_follow_up(): void
    {
        FirstTimer::create(['name' => 'Tolu Bello', 'phone' => '0803 111 2222', 'visited_on' => today()->subWeeks(3)]);
        FirstTimer::create(['name' => 'Tolu Bello', 'phone' => '+234 803 111 2222', 'visited_on' => today()]);

        $this->assertSame(['first_timer', 'second_visit'], FollowUp::orderBy('id')->pluck('reason')->all());
        $this->assertStringContainsString('Came back!', FollowUp::where('reason', 'second_visit')->sole()->note);
        $this->actingAs(User::factory()->create(['role' => 'pastor']))->get(route('admin.care.index', ['tab' => 'open']))->assertOk()
            ->assertSee('Second visit')->assertSee('believers\' class');
    }

    public function test_any_staff_member_registers_first_timers_and_new_members(): void
    {
        $usher = User::factory()->create(['role' => 'usher']);
        $office = User::factory()->create(['role' => 'secretary']);

        $this->actingAs($usher)->get(route('admin.register.index'))->assertOk()->assertSee('A first-timer')->assertSee('A new member');
        $this->post(route('admin.register.first-timer'), ['first_name' => 'Grace', 'last_name' => 'Ade', 'gender' => 'female', 'phone' => '0805 000 1111', 'visited_on' => today()->toDateString()])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Welcome card added for Grace Ade'));
        $this->assertSame(1, FollowUp::where('reason', 'first_timer')->count());

        // An usher's new member waits for approval; their unit is a request.
        $this->post(route('admin.register.member'), ['first_name' => 'John', 'last_name' => 'Eze', 'gender' => 'female', 'phone' => '0806 222 3333', 'unit_id' => $this->choir->id])
            ->assertSessionHas('status', 'John Eze is on the register, waiting for the church office to approve them.');
        $john = Member::where('name', 'John Eze')->sole();
        $this->assertSame([Member::PENDING, []], [$john->status, $john->unitIds()]);
        $this->assertSame($this->choir->id, $john->unitRequests()->value('unit_id'));
        // The same phone twice is caught.
        $this->post(route('admin.register.member'), ['first_name' => 'John', 'last_name' => 'Again', 'gender' => 'female', 'phone' => '+2348062223333'])->assertSessionHasErrors('phone', null, 'member');

        // The office's are approved straight away, in the unit.
        $this->actingAs($office)->post(route('admin.register.member'), ['first_name' => 'Mary', 'last_name' => 'Obi', 'gender' => 'female', 'phone' => '0807 444 5555', 'unit_id' => $this->choir->id])
            ->assertSessionHas('status', 'Mary Obi is on the register. They\'re in Choir.');
        $mary = Member::where('name', 'Mary Obi')->sole();
        $this->assertSame([Member::ACTIVE, 'worker'], [$mary->status, $mary->rank]);
    }

    public function test_visits_are_planned_reminded_and_reported_and_close_the_follow_up(): void
    {
        $pastor = User::factory()->create(['role' => 'pastor', 'name' => 'Pastor Femi']);
        $deacon = User::factory()->create(['role' => 'secretary', 'name' => 'Bro Kunle']);
        $followUp = FollowUp::create(['member_id' => $this->away->id, 'reason' => 'absent', 'due_on' => today(), 'reference_on' => $this->away->last_attended_on]);

        $this->actingAs($pastor)->get(route('admin.visits.index', ['follow_up' => $followUp->id]))->assertOk()->assertSee('For the follow-up with')->assertSee('Ada Obi');
        $this->post(route('admin.visits.store'), [
            'follow_up_id' => $followUp->id, 'purpose' => 'absent', 'scheduled_at' => now()->addDay()->setTime(17, 0)->format('Y-m-d H:i'),
            'visitors' => [$pastor->id, $deacon->id], 'notes' => 'Bring the bulletin', 'tell_them' => '1',
        ])->assertSessionHasNoErrors();
        $visit = Visit::sole();
        $this->assertSame([$this->away->id, 'Ada Obi'], [$visit->member_id, $visit->name]);
        Mail::assertSent(CareListMail::class, fn ($m) => $m->hasTo($deacon->email) && str_contains($m->intro, 'Going with you: Pastor Femi'));
        Mail::assertNotSent(CareListMail::class, fn ($m) => $m->hasTo($pastor->email)); // not told about their own plan
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($this->away->user->email) && str_contains($m->body, 'will visit you'));

        // The day before, everyone going is reminded.
        $this->artisan('care:scan')->expectsOutput('Sent reminders for 1 visits.');
        Mail::assertSent(CareListMail::class, fn ($m) => $m->hasTo($pastor->email) && str_starts_with($m->headline, 'Reminder: visit to Ada Obi'));
        $this->artisan('care:scan')->expectsOutput('Sent reminders for 0 visits.');

        $this->get(route('admin.visits.index', ['tab' => 'mine']))->assertOk()->assertSee('Ada Obi')->assertSee('Bring the bulletin');
        $this->post(route('admin.visits.complete', $visit), ['outcome' => 'prayed', 'report' => 'Her mother is ill. Prayed together; she will be back Sunday.'])->assertSessionHas('status');
        $this->assertSame('done', $visit->fresh()->status);
        $this->assertSame('prayed', $followUp->fresh()->outcome);
        $this->get(route('admin.visits.index', ['tab' => 'done']))->assertSee('Her mother is ill.');

        // Ushers don't do visitation.
        $this->actingAs(User::factory()->create(['role' => 'usher']))->get(route('admin.visits.index'))->assertForbidden();
    }

    public function test_visits_can_be_moved_and_cancelled(): void
    {
        $pastor = User::factory()->create(['role' => 'pastor']);
        $other = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($pastor)->post(route('admin.visits.store'), ['name' => 'Mrs Ade (hospital)', 'address' => 'LUTH, Ward 4', 'purpose' => 'hospital',
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i'), 'visitors' => [$pastor->id]])->assertSessionHasNoErrors();
        $visit = Visit::sole();
        $this->assertNull($visit->member_id);

        $this->put(route('admin.visits.update', $visit), ['scheduled_at' => now()->addDays(4)->format('Y-m-d H:i'), 'visitors' => [$pastor->id, $other->id]])->assertSessionHasNoErrors();
        Mail::assertSent(CareListMail::class, fn ($m) => $m->hasTo($other->email) && str_contains($m->headline, 'moved'));
        $this->post(route('admin.visits.cancel', $visit));
        $this->assertSame('cancelled', $visit->fresh()->status);
        $this->post(route('admin.visits.store'), ['purpose' => 'hospital', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'visitors' => [$pastor->id]])->assertSessionHasErrors('name');
    }

    public function test_overdue_digest_and_weekly_summary(): void
    {
        $carer = User::factory()->create(['role' => 'secretary']);
        $pastor = User::factory()->create(['role' => 'pastor']);
        FollowUp::create(['member_id' => $this->away->id, 'reason' => 'new_member', 'due_on' => today()->subDays(3), 'assigned_to' => $carer->id]);
        FollowUp::create(['member_id' => $this->hod->id, 'reason' => 'other', 'due_on' => today()->addDay(), 'assigned_to' => $carer->id]);

        $this->artisan('care:scan')->expectsOutput('Emailed 1 staff about overdue follow-ups.');
        Mail::assertSent(CareListMail::class, fn ($m) => $m->hasTo($carer->email) && $m->headline === '1 follow-up is overdue' && $m->items[0][0] === 'Ada Obi · New member');

        $this->artisan('care:weekly')->expectsOutput('Sent the weekly follow-up summary to 1 pastors.');
        Mail::assertSent(CareListMail::class, fn ($m) => $m->hasTo($pastor->email) && $m->headline === 'Follow-up this week' && $m->figures['Overdue'] === 1);

        config(['church.care.overdue_digest' => '0', 'church.care.weekly_summary' => '0']);
        $this->artisan('care:scan')->expectsOutput('Emailed 0 staff about overdue follow-ups.');
        $this->artisan('care:weekly')->expectsOutput('Sent the weekly follow-up summary to 0 pastors.');
    }
}

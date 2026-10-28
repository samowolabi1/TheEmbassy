<?php

namespace Tests\Feature;

use App\Mail\FollowUpAssigned;
use App\Models\FirstTimer;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sundays going back from last week, with someone (not $member) marked present at each so attendance counts as taken.
     *
     * @return list<Service>
     */
    private function sundays(int $count): array
    {
        $someone = Member::factory()->active()->create(['joined_on' => today()->subYears(2)]);
        $services = [];
        for ($i = $count; $i >= 1; $i--) {
            $service = Service::create(['held_on' => now()->previous('Sunday')->subWeeks($i - 1), 'kind' => 'sunday']);
            $service->members()->attach($someone->id);
            $services[] = $service;
        }

        return $services;
    }

    public function test_first_timer_cards_get_a_call_within_two_days(): void
    {
        $this->post(route('welcome.store'), ['name' => 'Bisi Eze', 'phone' => '0803 555 0000', 'wants_to_join' => '1']);

        $followUp = FollowUp::sole();
        $this->assertSame('first_timer', $followUp->reason);
        $this->assertTrue($followUp->due_on->isSameDay(today()->addDays(2)));
        $this->assertSame('Would like to become a member', $followUp->note);
    }

    public function test_completing_a_first_timer_follow_up_ticks_off_the_card(): void
    {
        $card = FirstTimer::create(['name' => 'Bisi', 'phone' => '0803', 'visited_on' => today()]);
        $this->actingAs($pastor = User::factory()->staff('pastor')->create());

        $this->post(route('admin.care.complete', FollowUp::sole()), ['outcome' => 'reached', 'outcome_note' => 'Coming Sunday'])->assertRedirect();

        $this->assertSame($pastor->id, $card->fresh()->followed_up_by);
        $this->assertSame('Coming Sunday', $card->fresh()->follow_up_note);
        $this->assertNotNull(FollowUp::sole()->done_at);
    }

    public function test_approving_a_sign_up_raises_a_welcome_but_imports_do_not(): void
    {
        $joined = Member::factory()->create();
        $this->actingAs(User::factory()->staff('secretary')->create())->post(route('admin.members.approve', $joined));

        $this->assertSame('new_member', FollowUp::sole()->reason);

        $this->post(route('admin.members.store'), ['name' => 'Added by office']);
        $this->assertSame(1, FollowUp::count());
    }

    public function test_members_who_miss_three_sundays_in_a_row_are_flagged_once(): void
    {
        $away = Member::factory()->active()->create(['name' => 'Away Member', 'joined_on' => today()->subYear(), 'last_attended_on' => today()->subMonths(2)]);
        $services = $this->sundays(3);
        $regular = Member::factory()->active()->create(['joined_on' => today()->subYear()]);
        foreach ($services as $s) {
            $s->members()->attach($regular->id);
        }

        $this->artisan('care:scan')->expectsOutput('Raised 1 follow-ups for members not seen lately; closed 0 for members who are back.');
        $this->artisan('care:scan')->expectsOutput('Raised 0 follow-ups for members not seen lately; closed 0 for members who are back.');

        $followUp = FollowUp::sole();
        $this->assertSame($away->id, $followUp->member_id);
        $this->assertStringContainsString('Missed the last 3 Sundays', $followUp->note);
    }

    public function test_one_or_two_missed_sundays_are_not_flagged(): void
    {
        Member::factory()->active()->create(['joined_on' => today()->subYear()]);
        $services = $this->sundays(3);
        $member = Member::factory()->active()->create(['joined_on' => today()->subYear()]);
        $services[0]->members()->attach($member->id); // was there three weeks ago

        $this->artisan('care:scan');

        $this->assertSame(0, FollowUp::where('member_id', $member->id)->count());
    }

    public function test_sundays_without_attendance_taken_do_not_count_as_missed(): void
    {
        Member::factory()->active()->create(['joined_on' => today()->subYear()]);
        foreach (range(1, 3) as $i) {
            Service::create(['held_on' => now()->previous('Sunday')->subWeeks($i - 1), 'kind' => 'sunday', 'in_person' => 400]);
        }

        $this->artisan('care:scan');

        $this->assertSame(0, FollowUp::count());
    }

    public function test_new_members_are_not_flagged_for_sundays_before_they_joined(): void
    {
        $this->sundays(3);
        Member::factory()->active()->create(['joined_on' => today()]);

        $this->artisan('care:scan');

        $this->assertSame(0, FollowUp::where('reason', 'absent')->count());
    }

    public function test_coming_back_to_any_service_closes_the_follow_up_without_raising_another(): void
    {
        $away = Member::factory()->active()->create(['joined_on' => today()->subYear(), 'last_attended_on' => today()->subMonths(2)]);
        $this->sundays(3);
        $this->artisan('care:scan');

        $away->forceFill(['last_attended_on' => today()])->save();
        $this->artisan('care:scan')->expectsOutput('Raised 0 follow-ups for members not seen lately; closed 1 for members who are back.');
        $this->assertSame('returned', FollowUp::sole()->outcome);
    }

    public function test_the_threshold_is_set_by_the_church(): void
    {
        config(['church.care.absent_after' => 2]);
        Member::factory()->active()->create(['joined_on' => today()->subYear()]);
        $this->sundays(2);

        $this->artisan('care:scan');

        $this->assertSame(1, FollowUp::where('reason', 'absent')->count());
    }

    public function test_care_team_assigns_and_gets_emailed(): void
    {
        Mail::fake();
        $member = Member::factory()->active()->create(['name' => 'Tolu Adeyemi', 'phone' => '08030000000']);
        $pastor = User::factory()->staff('pastor')->create();
        $office = User::factory()->staff('secretary')->create();
        $this->actingAs($office);

        $this->post(route('admin.care.store'), ['member_id' => $member->id, 'note' => 'Lost her job; pray with her', 'due_on' => today()->addDay()->toDateString(), 'assigned_to' => $pastor->id])->assertRedirect();
        Mail::assertSent(FollowUpAssigned::class, fn ($mail) => $mail->hasTo($pastor->email));

        $this->actingAs($pastor)->get(route('admin.care.index'))->assertSee('Tolu Adeyemi')->assertSee('Lost her job')->assertSee('wa.me/2348030000000', false);
        $this->get('/admin')->assertSee('Your follow-ups');
        $this->get(route('admin.members.show', $member))->assertSee('Lost her job');
    }

    public function test_only_care_staff_can_be_assigned_and_ushers_have_no_access(): void
    {
        $usher = User::factory()->staff('usher')->create();
        $member = Member::factory()->active()->create();
        $this->actingAs(User::factory()->staff('pastor')->create());

        $this->post(route('admin.care.store'), ['member_id' => $member->id, 'note' => 'x', 'due_on' => today()->toDateString(), 'assigned_to' => $usher->id])->assertSessionHasErrors('assigned_to');
        $this->actingAs($usher)->get(route('admin.care.index'))->assertForbidden();
    }

    public function test_member_profile_shows_recent_sunday_attendance(): void
    {
        $services = $this->sundays(4);
        $member = Member::factory()->active()->create();
        $services[1]->members()->attach($member->id);
        $services[3]->members()->attach($member->id);

        $this->actingAs(User::factory()->staff('pastor')->create())->get(route('admin.members.show', $member))->assertSee('At <strong>2 of the last 4</strong>', false);
    }
}

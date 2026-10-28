<?php

namespace Tests\Feature;

use App\Models\FirstTimer;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function usher(): User
    {
        return User::factory()->staff('usher')->create();
    }

    public function test_ushers_record_a_service_and_see_the_chart(): void
    {
        $this->actingAs($this->usher());
        $sunday = now()->previous('Sunday')->toDateString();

        $this->post(route('admin.attendance.store'), ['held_on' => $sunday, 'kind' => 'sunday', 'in_person' => 584, 'online' => 336])
            ->assertRedirect(route('admin.attendance.show', Service::sole()));

        $this->get(route('admin.attendance.index'))->assertSee('920')->assertSee('Sunday attendance');
        $this->post(route('admin.attendance.store'), ['held_on' => $sunday, 'kind' => 'sunday', 'in_person' => 1, 'online' => 0])->assertSessionHasErrors('held_on');
        $this->assertSame(1, Service::count());
    }

    public function test_marking_members_present_updates_last_attended(): void
    {
        $this->actingAs($this->usher());
        [$ada, $jide] = Member::factory()->active()->count(2)->create();
        $old = Service::create(['held_on' => now()->subWeeks(2), 'kind' => 'sunday', 'in_person' => 10]);
        $new = Service::create(['held_on' => now()->subWeek(), 'kind' => 'sunday', 'in_person' => 10]);

        $this->post(route('admin.attendance.mark', $old), ['shown' => [$ada->id, $jide->id], 'present' => [$ada->id, $jide->id]]);
        $this->post(route('admin.attendance.mark', $new), ['shown' => [$ada->id, $jide->id], 'present' => [$ada->id]])->assertSessionHas('status', '1 marked present.');

        $this->assertTrue($ada->fresh()->last_attended_on->isSameDay($new->held_on));
        $this->assertTrue($jide->fresh()->last_attended_on->isSameDay($old->held_on));

        // Unticking Ada at the newer service takes her back to the older one.
        $this->post(route('admin.attendance.mark', $new), ['shown' => [$ada->id], 'present' => []]);
        $this->assertTrue($ada->fresh()->last_attended_on->isSameDay($old->held_on));
        $this->get(route('admin.members.index'))->assertForbidden(); // ushers don't see the register
    }

    public function test_marking_only_touches_members_shown(): void
    {
        $this->actingAs($this->usher());
        [$ada, $jide] = Member::factory()->active()->count(2)->create();
        $service = Service::create(['held_on' => today(), 'kind' => 'midweek']);
        $service->members()->attach($jide->id);

        $this->post(route('admin.attendance.mark', $service), ['shown' => [$ada->id], 'present' => [$ada->id]]);

        $this->assertEqualsCanonicalizing([$ada->id, $jide->id], $service->members()->pluck('members.id')->all());
    }

    public function test_visitors_fill_in_a_first_timer_card(): void
    {
        $this->get(route('welcome'))->assertOk()->assertSee('Say hello');
        $this->post(route('welcome.store'), ['name' => 'Bisi Eze', 'phone' => '0803 555 0000', 'invited_by' => 'Ada O.', 'wants_to_join' => '1'])
            ->assertRedirect(route('welcome'))->assertSessionHas('welcomed', 'Bisi');

        $card = FirstTimer::sole();
        $this->assertTrue($card->visited_on->isToday());
        $this->assertTrue($card->wants_to_join);

        $this->post(route('welcome.store'), ['name' => 'Bot', 'phone' => '1', 'website' => 'x']);
        $this->assertSame(1, FirstTimer::count());
    }

    public function test_follow_up_and_adding_to_the_register(): void
    {
        $card = FirstTimer::create(['name' => 'Bisi Eze', 'phone' => '0803 555 0000', 'visited_on' => today(), 'invited_by' => 'Ada O.']);
        $this->actingAs($office = User::factory()->staff('secretary')->create());

        $this->get(route('admin.first-timers.index'))->assertSee('Bisi Eze')->assertSee('Invited by Ada O.');
        $this->get('/admin')->assertOk();

        $this->post(route('admin.first-timers.follow-up', $card), ['note' => 'Coming again Sunday'])->assertRedirect();
        $card->refresh();
        $this->assertSame($office->id, $card->followed_up_by);
        $this->get(route('admin.first-timers.index'))->assertDontSee('0803 555 0000');
        $this->get(route('admin.first-timers.index', ['tab' => 'done']))->assertSee('Coming again Sunday');

        $this->post(route('admin.first-timers.register', $card))->assertRedirect(route('admin.members.index', ['tab' => 'pending']));
        $member = Member::sole();
        $this->assertSame(Member::PENDING, $member->status);
        $this->assertStringContainsString('invited by Ada O.', $member->notes);
        $this->post(route('admin.first-timers.register', $card));
        $this->assertSame(1, Member::count());
    }

    public function test_ushers_cannot_add_first_timers_to_the_register(): void
    {
        $card = FirstTimer::create(['name' => 'Bisi', 'phone' => '1', 'visited_on' => today()]);

        $this->actingAs($this->usher())->post(route('admin.first-timers.register', $card))->assertForbidden();
    }
}

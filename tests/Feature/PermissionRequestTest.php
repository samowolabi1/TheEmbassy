<?php

namespace Tests\Feature;

use App\Mail\ReminderMail;
use App\Models\Duty;
use App\Models\Member;
use App\Models\PermissionRequest;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Services\Care\FollowUps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PermissionRequestTest extends TestCase
{
    use RefreshDatabase;

    private Unit $choir;

    private Member $hod;

    private Member $ada;

    private User $pastor;

    private Service $sunday;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->choir = Unit::factory()->create(['name' => 'Choir']);
        $this->hod = Member::factory()->active()->create(['unit_id' => $this->choir->id, 'name' => 'Samuel Ojo']);
        $this->choir->update(['hod_member_id' => $this->hod->id]);
        $this->ada = Member::factory()->active()->create(['unit_id' => $this->choir->id, 'name' => 'Ada Obi']);
        $this->pastor = User::factory()->staff('pastor')->create(['email' => 'pastor@example.com']);
        $this->sunday = Service::create(['held_on' => today()->next('Sunday'), 'kind' => 'sunday', 'starts_at' => today()->next('Sunday')->setTime(8, 0)]);
    }

    private function ask(array $data = []): TestResponse
    {
        return $this->actingAs($this->ada->user)->post(route('account.permission.store'), [
            'kind' => 'absent', 'service_id' => $this->sunday->id, 'reason' => 'Travelling to Ibadan for my sister\'s wedding.', ...$data,
        ]);
    }

    public function test_a_worker_asks_and_their_hod_and_the_pastors_are_told(): void
    {
        $this->actingAs($this->ada->user)->get(route('account'))->assertSee(route('account.permission'), false);
        $this->get(route('account.permission'))->assertOk()->assertSee('Won\'t be in church')->assertSee('Coming late');

        $this->ask()->assertRedirect(route('account.permission'))->assertSessionHasNoErrors();

        $request = PermissionRequest::sole();
        $this->assertSame(['absent', 'pending', $this->sunday->held_on->toDateString()], [$request->kind, $request->status, $request->on_date->toDateString()]);
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($this->hod->user->email) && str_contains($m->body, 'Ibadan') && str_contains($m->url, '/units/'));
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo('pastor@example.com') && str_contains($m->url, '/admin/permissions'));
        Mail::assertNotSent(ReminderMail::class, fn ($m) => $m->hasTo($this->ada->user->email));

        // The HOD sees it in the unit area, with a count in the menu; so does the pastor in admin.
        $this->actingAs($this->hod->user)->get(route('unit.permissions', $this->choir))->assertOk()->assertSee('Ada Obi')->assertSee('Ibadan');
        $this->actingAs($this->pastor)->get(route('admin.permissions.index'))->assertOk()->assertSee('Ada Obi')->assertSee('Choir');
    }

    public function test_the_hod_grants_it_and_the_worker_hears_back(): void
    {
        $this->ask();
        $request = PermissionRequest::sole();

        $this->actingAs($this->hod->user)->post(route('unit.permissions.answer', [$this->choir, $request]), ['status' => 'granted', 'note' => 'Safe trip. Bro. Femi will lead.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['granted', $this->hod->user->id], [$request->fresh()->status, $request->fresh()->answered_by]);
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($this->ada->user->email) && str_contains($m->body, 'Safe trip'));

        $this->actingAs($this->ada->user)->get(route('account.permission'))->assertSee('Permission granted')->assertSee('Safe trip');
        $this->actingAs($this->pastor)->get(route('admin.permissions.index'))->assertSee('Granted by');
    }

    public function test_coming_late_says_about_when(): void
    {
        $this->ask(['kind' => 'late', 'arrive_at' => '09:30', 'reason' => 'Night shift ends at 8.'])->assertSessionHasNoErrors();
        $this->assertSame('Coming late (about 9:30 AM) · Sunday service, '.$this->sunday->held_on->format('D j M'), PermissionRequest::sole()->load('service')->summary());
    }

    public function test_a_day_without_a_listed_service_works_too(): void
    {
        $this->ask(['service_id' => '', 'on_date' => today()->addDays(3)->toDateString()])->assertSessionHasNoErrors();
        $this->assertNull(PermissionRequest::sole()->service_id);
    }

    public function test_it_needs_a_reason_and_only_once_per_service(): void
    {
        $this->ask(['reason' => ''])->assertSessionHasErrors('reason');
        $this->ask()->assertSessionHasNoErrors();
        $this->ask()->assertSessionHasErrors('service_id');
        $this->ask(['service_id' => '', 'on_date' => today()->subDay()->toDateString()])->assertSessionHasErrors('on_date');
    }

    public function test_the_worker_can_cancel(): void
    {
        $this->ask();
        $this->actingAs($this->ada->user)->post(route('account.permission.cancel', PermissionRequest::sole()))->assertRedirect();
        $this->assertSame('cancelled', PermissionRequest::sole()->status);
        $this->actingAs($this->hod->user)->get(route('unit.permissions', $this->choir))->assertDontSee('Ibadan');
    }

    public function test_only_workers_ask_and_only_their_leaders_and_pastors_answer(): void
    {
        $member = Member::factory()->active()->create();
        $this->actingAs($member->user)->get(route('account.permission'))->assertForbidden();
        $this->get(route('account'))->assertDontSee(route('account.permission'), false);

        $this->ask();
        $request = PermissionRequest::sole();

        // The church office doesn't see these; another unit's HOD can't answer them.
        $this->actingAs(User::factory()->staff('secretary')->create())->get(route('admin.permissions.index'))->assertForbidden();
        $ushers = Unit::factory()->create(['name' => 'Ushers']);
        $usherHod = Member::factory()->active()->create(['unit_id' => $ushers->id]);
        $ushers->update(['hod_member_id' => $usherHod->id]);
        $this->actingAs($usherHod->user)->post(route('unit.permissions.answer', [$ushers, $request]), ['status' => 'granted'])->assertNotFound();
        $this->actingAs($usherHod->user)->get(route('unit.permissions', $ushers))->assertDontSee('Ada Obi');
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_the_card_warns_when_they_are_on_the_rota(): void
    {
        Duty::forceCreate(['service_id' => $this->sunday->id, 'member_id' => $this->ada->id, 'unit_id' => $this->choir->id, 'role' => 'Lead singer']);
        $this->ask();
        $this->actingAs($this->hod->user)->get(route('unit.permissions', $this->choir))->assertSee('On the rota for this service: Lead singer (Choir)');
    }

    public function test_workers_with_permission_are_not_followed_up_for_missing_those_sundays(): void
    {
        config(['church.care.absent_after' => 2]);
        $someone = Member::factory()->active()->create();
        $sundays = collect([2, 1])->map(fn ($w) => tap(Service::create(['held_on' => today()->previous('Sunday')->subWeeks($w - 1), 'kind' => 'sunday']), fn ($s) => $s->members()->attach($someone->id)));
        $this->ada->update(['joined_on' => today()->subYear()]);
        $this->hod->update(['joined_on' => today()->subYear()]);

        PermissionRequest::create(['member_id' => $this->ada->id, 'service_id' => $sundays->last()->id, 'on_date' => $sundays->last()->held_on, 'kind' => 'absent', 'reason' => 'Away on a course'])
            ->forceFill(['status' => 'granted'])->save();

        [$absent] = app(FollowUps::class)->absentFor(2);
        $this->assertTrue($absent->contains('id', $this->hod->id));
        $this->assertFalse($absent->contains('id', $this->ada->id));
    }
}

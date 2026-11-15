<?php

namespace Tests\Feature;

use App\Mail\CampaignMail;
use App\Mail\ReminderMail;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberRanksTest extends TestCase
{
    use RefreshDatabase;

    private Unit $choir;

    private Member $hod;

    private Member $ada;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->choir = Unit::factory()->create(['name' => 'Choir']);
        $this->hod = Member::factory()->active()->create(['unit_id' => $this->choir->id, 'name' => 'Samuel Ojo']);
        $this->choir->update(['hod_member_id' => $this->hod->id]);
        $this->ada = Member::factory()->active()->create(['unit_id' => $this->choir->id, 'name' => 'Ada Obi']);
    }

    public function test_a_unit_head_makes_a_member_a_worker_and_back(): void
    {
        $this->actingAs($this->hod->user)->get(route('unit.members', $this->choir))->assertOk()->assertSee('Make a worker');
        $this->post(route('unit.members.rank', [$this->choir, $this->ada]), ['rank' => 'worker'])->assertSessionHas('status', 'Ada Obi is now a worker in Choir.');
        $this->ada->refresh();
        $this->assertSame(['worker', $this->hod->user_id], [$this->ada->rank, $this->ada->rank_changed_by]);
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($this->ada->user->email) && str_contains($m->body, 'worker in Choir'));
        $this->actingAs($this->ada->user)->get(route('account'))->assertOk()->assertSee('Worker · Choir');

        // Heads can't make ministers.
        $this->actingAs($this->hod->user)->post(route('unit.members.rank', [$this->choir, $this->ada]), ['rank' => 'minister'])->assertSessionHasErrors('rank');
        $this->post(route('unit.members.rank', [$this->choir, $this->ada]), ['rank' => 'member']);
        $this->assertSame('member', $this->ada->fresh()->rank);

        // Not for members of other units.
        $other = Member::factory()->active()->create(['unit_id' => Unit::factory()->create()->id]);
        $this->post(route('unit.members.rank', [$this->choir, $other]), ['rank' => 'worker'])->assertNotFound();
        // An ordinary member can't open the unit area at all.
        $this->actingAs($this->ada->user)->post(route('unit.members.rank', [$this->choir, $this->ada]), ['rank' => 'worker'])->assertForbidden();
    }

    public function test_a_pastor_makes_a_worker_a_minister(): void
    {
        $pastor = User::factory()->create(['role' => 'pastor']);
        $office = User::factory()->create(['role' => 'secretary']);

        // Ministers come from the workers.
        $this->actingAs($pastor)->post(route('admin.members.rank', $this->ada), ['rank' => 'minister'])->assertSessionHasErrors('rank');
        $this->actingAs($this->hod->user)->post(route('unit.members.rank', [$this->choir, $this->ada]), ['rank' => 'worker']);

        // The office can't, the pastor can.
        $this->actingAs($office)->post(route('admin.members.rank', $this->ada), ['rank' => 'minister'])->assertForbidden();
        $this->actingAs($pastor)->get(route('admin.members.show', $this->ada))->assertOk()->assertSee('Make a minister');
        $this->post(route('admin.members.rank', $this->ada), ['rank' => 'minister'])->assertSessionHasNoErrors();
        $this->assertSame('minister', $this->ada->fresh()->rank);
        Mail::assertSent(ReminderMail::class, fn ($m) => str_contains($m->body, 'made you a minister'));

        // A head can't undo a minister; leaving the unit doesn't change a minister.
        $this->actingAs($this->hod->user)->post(route('unit.members.rank', [$this->choir, $this->ada]), ['rank' => 'member'])->assertForbidden();
        $this->delete(route('unit.members.remove', [$this->choir, $this->ada]));
        $this->assertSame('minister', $this->ada->fresh()->rank);

        // The list filters by role.
        $this->actingAs($pastor)->get(route('admin.members.index', ['rank' => 'minister']))->assertOk()->assertSee('Ada Obi')->assertDontSee('Samuel Ojo');
    }

    public function test_workers_must_be_in_a_unit_and_leave_it_as_members(): void
    {
        $pastor = User::factory()->create(['role' => 'pastor']);
        $loose = Member::factory()->active()->create();
        $this->actingAs($pastor)->post(route('admin.members.rank', $loose), ['rank' => 'worker'])->assertSessionHasErrors('rank');
        $pending = Member::factory()->create(['unit_id' => $this->choir->id]);
        $this->post(route('admin.members.rank', $pending), ['rank' => 'worker'])->assertSessionHasErrors('rank');

        $this->post(route('admin.members.rank', $this->ada), ['rank' => 'worker']);
        $this->actingAs($this->hod->user)->delete(route('unit.members.remove', [$this->choir, $this->ada]));
        $this->assertSame(['member', null], [$this->ada->fresh()->rank, $this->ada->fresh()->unit_id]);
    }

    public function test_the_message_centre_reaches_workers_and_ministers(): void
    {
        $worker = Member::factory()->active()->create(['unit_id' => $this->choir->id]);
        $worker->forceFill(['rank' => 'worker'])->save();
        $minister = Member::factory()->active()->create();
        $minister->forceFill(['rank' => 'minister'])->save();
        $office = User::factory()->create(['role' => 'secretary']);

        $this->actingAs($office);
        $this->getJson(route('admin.outreach.reach', ['type' => 'members', 'rank' => 'workers']))->assertJson(['people' => 1]);
        $this->getJson(route('admin.outreach.reach', ['type' => 'members', 'rank' => 'ministers']))->assertJson(['people' => 1]);
        $this->getJson(route('admin.outreach.reach', ['type' => 'members', 'rank' => 'leaders']))->assertJson(['people' => 2]);
        $this->getJson(route('admin.outreach.reach', ['type' => 'members', 'rank' => 'members']))->assertJson(['people' => 2]);
        $this->get(route('admin.outreach.create'))->assertOk()->assertSee('Workers and ministers');
        $this->get(route('admin.outreach.contacts', ['type' => 'leaders']))->assertOk()->assertSee($worker->name)->assertSee('Minister');
        $this->get(route('admin.outreach.index'))->assertOk()->assertSee('Workers &amp; ministers', false);

        $this->post(route('admin.outreach.store'), ['subject' => 'Workers meeting', 'body' => 'Hi {first_name}', 'channels' => ['email'], 'audience' => ['type' => 'members', 'rank' => 'leaders'], 'action' => 'send'])->assertRedirect();
        $this->artisan('messages:send');
        Mail::assertSent(CampaignMail::class, 2);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo($minister->user->email));
    }
}

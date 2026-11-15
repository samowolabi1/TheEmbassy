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

    private const ALL_DONE = ['baptised' => '1', 'believers_class' => '1', 'workers_training' => '1'];

    public function test_everyone_in_a_unit_is_a_worker_and_leaving_makes_them_a_member(): void
    {
        $this->assertSame('worker', $this->ada->rank);
        // Pending people aren't members yet, so not workers either, until approved.
        $pending = Member::factory()->create(['unit_id' => $this->choir->id]);
        $this->assertSame('member', $pending->fresh()->rank);
        $pending->approve();
        $this->assertSame('worker', $pending->fresh()->rank);

        $this->actingAs($this->hod->user)->delete(route('unit.members.remove', [$this->choir, $this->ada]));
        $this->assertSame(['member', null], [$this->ada->fresh()->rank, $this->ada->fresh()->unit_id]);
    }

    public function test_a_unit_head_adds_a_worker_with_their_training_answers(): void
    {
        $bola = Member::factory()->active()->create(['name' => 'Bola Ade']);
        $this->actingAs($this->hod->user)->get(route('unit.members', $this->choir))->assertOk()->assertSee('Workers\' in training class')->assertDontSee('Make a worker');

        // Every question must be answered.
        $this->post(route('unit.members.add', $this->choir), ['member_id' => $bola->id, 'training' => ['baptised' => '1']])->assertSessionHasErrors('training.believers_class');
        $this->post(route('unit.members.add', $this->choir), ['member_id' => $bola->id, 'training' => ['baptised' => '1', 'believers_class' => '1', 'workers_training' => '0']])
            ->assertSessionHas('status', 'Bola Ade added to Choir and is now a worker. Still to do: Workers\' in training class.');
        $bola->refresh();
        $this->assertSame(['worker', true, false], [$bola->rank, $bola->believers_class, $bola->workers_training]);
        $this->assertFalse($bola->needsTrainingAnswers());
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($bola->user->email) && str_contains($m->body, 'worker in Choir'));

        // The head updates it later.
        $this->post(route('unit.members.training', [$this->choir, $bola]), ['training' => self::ALL_DONE])->assertSessionHasNoErrors();
        $this->assertSame([], $bola->fresh()->missingRequirements());
        // Not for members of other units, and not for ordinary members.
        $other = Member::factory()->active()->create(['unit_id' => Unit::factory()->create()->id]);
        $this->post(route('unit.members.training', [$this->choir, $other]), ['training' => self::ALL_DONE])->assertNotFound();
        $this->actingAs($this->ada->user)->post(route('unit.members.training', [$this->choir, $bola]), ['training' => self::ALL_DONE])->assertForbidden();
    }

    public function test_a_new_worker_answers_their_training_in_the_app(): void
    {
        $this->assertTrue($this->ada->needsTrainingAnswers());
        $this->actingAs($this->ada->user)->get(route('account'))->assertOk()->assertSee('You\'re now a worker in Choir', false)->assertSee('Choir');
        $this->post(route('account.training'), ['training' => ['baptised' => '1', 'believers_class' => '0', 'workers_training' => '0']])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Still to do: Believers\' class, Workers\' in training class'));
        $this->assertFalse($this->ada->fresh()->needsTrainingAnswers());
        $this->get(route('account'))->assertOk()->assertDontSee('You\'re now a worker', false)->assertSee('Update my training');

        // Members who aren't workers have nothing to answer.
        $plain = Member::factory()->active()->create();
        $this->actingAs($plain->user)->post(route('account.training'), ['training' => self::ALL_DONE])->assertNotFound();

        // Staff find who hasn't answered.
        $pastor = User::factory()->create(['role' => 'pastor']);
        $this->actingAs($pastor)->get(route('admin.members.index', ['training' => 'unanswered']))->assertOk()->assertSee('Samuel Ojo')->assertDontSee('Ada Obi');
        $this->get(route('admin.members.index', ['training' => 'missing']))->assertOk()->assertSee('Ada Obi');
    }

    public function test_a_pastor_makes_a_worker_a_minister_with_the_ministers_questions(): void
    {
        $pastor = User::factory()->create(['role' => 'pastor']);
        $office = User::factory()->create(['role' => 'secretary']);
        $answers = ['rank' => 'minister', 'training' => self::ALL_DONE + ['sod' => '1', 'bible_college' => '0']];

        // Ministers come from the workers.
        $loose = Member::factory()->active()->create();
        $this->actingAs($pastor)->post(route('admin.members.rank', $loose), $answers)->assertSessionHasErrors('rank');
        // The questions must be answered.
        $this->post(route('admin.members.rank', $this->ada), ['rank' => 'minister', 'training' => self::ALL_DONE])->assertSessionHasErrors('training.sod');

        // The office can't, the pastor can.
        $this->actingAs($office)->post(route('admin.members.rank', $this->ada), $answers)->assertForbidden();
        $this->actingAs($pastor)->get(route('admin.members.show', $this->ada))->assertOk()->assertSee('Make a minister')->assertSee('School of Disciples (SOD)');
        $this->post(route('admin.members.rank', $this->ada), $answers)->assertSessionHas('status', 'Ada Obi is now: Minister. Still to do: Bible college.');
        $ada = $this->ada->fresh();
        $this->assertSame(['minister', true, false, $pastor->id], [$ada->rank, $ada->sod, $ada->bible_college, $ada->rank_changed_by]);
        $this->assertFalse($ada->needsTrainingAnswers());
        Mail::assertSent(ReminderMail::class, fn ($m) => str_contains($m->body, 'made you a minister') && str_contains($m->body, 'Bible college'));

        // Leaving the unit doesn't change a minister; a pastor can make them a minister no longer.
        $this->actingAs($this->hod->user)->delete(route('unit.members.remove', [$this->choir, $this->ada]));
        $this->assertSame('minister', $this->ada->fresh()->rank);
        $this->actingAs($pastor)->get(route('admin.members.index', ['rank' => 'minister']))->assertOk()->assertSee('Ada Obi')->assertDontSee('Samuel Ojo');
        $this->post(route('admin.members.rank', $this->ada), ['rank' => 'worker']);
        $this->assertSame('member', $this->ada->fresh()->rank); // no longer in a unit
    }

    public function test_a_worker_can_serve_in_several_units(): void
    {
        $ushering = Unit::factory()->create(['name' => 'Ushering']);
        $usherHead = Member::factory()->active()->create();
        $ushering->update(['hod_member_id' => $usherHead->id]);
        $usherHead->joinUnit($ushering);

        // Ada (Choir) joins Ushering too, and is in both.
        $this->actingAs($usherHead->user)->get(route('unit.members', $ushering))->assertOk()->assertSee('Ada Obi (also in Choir)');
        $this->post(route('unit.members.add', $ushering), ['member_id' => $this->ada->id, 'training' => self::ALL_DONE])->assertSessionHasNoErrors();
        $ada = $this->ada->fresh();
        $this->assertSame([$this->choir->id, $ushering->id], $ada->unitIds());
        $this->assertSame([$this->choir->id, 'Worker · Choir, Ushering'], [$ada->unit_id, $ada->rankLine()]);

        // Each unit's messages reach her, and both rosters list her.
        $this->assertTrue($ushering->members()->whereKey($ada->id)->exists());
        $this->actingAs($this->hod->user)->get(route('unit.members', $this->choir))->assertSee('Ada Obi');

        // Leaving one unit keeps her a worker, with Ushering as her main unit; leaving the last makes her a member.
        $this->delete(route('unit.members.remove', [$this->choir, $ada]));
        $this->assertSame(['worker', $ushering->id], [$ada->fresh()->rank, $ada->fresh()->unit_id]);
        $this->actingAs($usherHead->user)->delete(route('unit.members.remove', [$ushering, $ada]));
        $this->assertSame(['member', null, []], [$ada->fresh()->rank, $ada->fresh()->unit_id, $ada->fresh()->unitIds()]);

        // The office ticks several units on the profile.
        $office = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($office)->put(route('admin.members.update', $ada), ['name' => 'Ada Obi', 'status' => 'active', 'units' => [$this->choir->id, $ushering->id]])->assertSessionHasNoErrors();
        $this->assertSame(['worker', [$this->choir->id, $ushering->id]], [$ada->fresh()->rank, $ada->fresh()->unitIds()]);
        $this->get(route('admin.members.index', ['unit' => $ushering->id]))->assertOk()->assertSee('Ada Obi');
        // A head stays in the unit they lead even if it's unticked.
        $this->put(route('admin.members.update', $this->hod), ['name' => 'Samuel Ojo', 'status' => 'active', 'units' => []]);
        $this->assertTrue($this->hod->fresh()->inUnit($this->choir));
    }

    public function test_someone_heads_one_unit_only_and_a_minister_can_head_one(): void
    {
        $admin = User::factory()->create(['role' => 'super']);
        $drama = Unit::factory()->create(['name' => 'Drama']);
        $this->actingAs($admin)->get(route('admin.units.index'))->assertOk()->assertSee('Samuel Ojo (heads Choir)');
        $this->put(route('admin.units.update', $drama), ['name' => 'Drama', 'hod_member_id' => $this->hod->id])
            ->assertSessionHasErrors(['hod_member_id' => 'Samuel Ojo already heads Choir. Someone can head one unit only: choose someone else, or change Choir\'s HOD first.'], null, 'unit');
        $this->assertNull($drama->fresh()->hod_member_id);

        // A minister can head a unit, and becomes part of it.
        $minister = Member::factory()->active()->create(['name' => 'Grace Eze']);
        $minister->forceFill(['rank' => 'minister'])->save();
        $this->put(route('admin.units.update', $drama), ['name' => 'Drama', 'hod_member_id' => $minister->id])->assertSessionHasNoErrors();
        $minister->refresh();
        $this->assertSame(['minister', [$drama->id]], [$minister->rank, $minister->unitIds()]);
        // ...and can also serve in another unit.
        $this->actingAs($this->hod->user)->post(route('unit.members.add', $this->choir), ['member_id' => $minister->id, 'training' => self::ALL_DONE + ['sod' => '1', 'bible_college' => '1']])->assertSessionHasNoErrors();
        $this->assertSame('minister', $minister->fresh()->rank);
        $this->assertTrue($minister->fresh()->inUnit($this->choir));
    }

    public function test_the_message_centre_reaches_workers_and_ministers(): void
    {
        $worker = Member::factory()->active()->create(['unit_id' => $this->choir->id]);
        $minister = Member::factory()->active()->create();
        $minister->forceFill(['rank' => 'minister'])->save();
        $office = User::factory()->create(['role' => 'secretary']);

        $this->actingAs($office);
        $this->getJson(route('admin.outreach.reach', ['type' => 'members', 'rank' => 'workers']))->assertJson(['people' => 3]);
        $this->getJson(route('admin.outreach.reach', ['type' => 'members', 'rank' => 'ministers']))->assertJson(['people' => 1]);
        $this->getJson(route('admin.outreach.reach', ['type' => 'members', 'rank' => 'leaders']))->assertJson(['people' => 4]);
        $this->getJson(route('admin.outreach.reach', ['type' => 'members', 'rank' => 'members']))->assertJson(['people' => 0]);
        $this->get(route('admin.outreach.create'))->assertOk()->assertSee('Workers and ministers');
        $this->get(route('admin.outreach.contacts', ['type' => 'leaders']))->assertOk()->assertSee($worker->name)->assertSee('Minister');
        $this->get(route('admin.outreach.index'))->assertOk()->assertSee('Workers &amp; ministers', false);

        $this->post(route('admin.outreach.store'), ['subject' => 'Workers meeting', 'body' => 'Hi {first_name}', 'channels' => ['email'], 'audience' => ['type' => 'members', 'rank' => 'leaders'], 'action' => 'send'])->assertRedirect();
        $this->artisan('messages:send');
        Mail::assertSent(CampaignMail::class, 4);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo($minister->user->email));
    }
}

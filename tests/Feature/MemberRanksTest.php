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
        $this->actingAs($this->ada->user)->get(route('account'))->assertOk()->assertSee('You\'re now a worker in Choir', false)->assertSee('Worker · Choir');
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

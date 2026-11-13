<?php

namespace Tests\Feature;

use App\Mail\SuggestionAnswered;
use App\Models\Member;
use App\Models\Service;
use App\Models\ServiceFeedback;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private Member $ada;

    private Service $sunday;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->ada = Member::factory()->active()->create(['name' => 'Ada Obi']);
        $this->sunday = Service::create(['held_on' => today()->subDay(), 'starts_at' => now()->subDay()->setTime(9, 0), 'kind' => 'sunday']);
        $this->ada->services()->attach($this->sunday->id, ['mode' => 'in_person', 'checked_in_at' => now()->subDay()]);
    }

    public function test_members_are_asked_after_a_service_they_attended_and_give_feedback_once(): void
    {
        $this->actingAs($this->ada->user)->get(route('account'))->assertSee('How was Sunday service?');
        $this->get(route('account.feedback'))->assertOk()->assertSee('you were there');

        $this->post(route('account.feedback.rate', $this->sunday), [])->assertSessionHasErrors('rating');
        $this->post(route('account.feedback.rate', $this->sunday), [
            'rating' => 5, 'went_well' => ['worship', 'word'], 'could_improve' => ['sound'], 'comment' => 'The word on purpose was exactly what I needed.',
        ])->assertRedirect(route('account.feedback'))->assertSessionHasNoErrors();
        // Sending again updates the same feedback.
        $this->post(route('account.feedback.rate', $this->sunday), ['rating' => 4, 'could_improve' => ['sound', 'timing'], 'anonymous' => '1']);
        $f = ServiceFeedback::sole();
        $this->assertSame([4, ['sound', 'timing'], true], [$f->rating, $f->could_improve, $f->anonymous]);
        $this->get(route('account'))->assertDontSee('How was Sunday service?');

        // Old services and unknown parts are refused.
        $old = Service::create(['held_on' => today()->subDays(ServiceFeedback::DAYS + 2), 'kind' => 'sunday']);
        $this->post(route('account.feedback.rate', $old), ['rating' => 3])->assertNotFound();
        $this->post(route('account.feedback.rate', $this->sunday), ['rating' => 3, 'went_well' => ['nonsense']])->assertSessionHasErrors('went_well.0');
    }

    public function test_staff_see_each_services_results_without_names_when_asked(): void
    {
        $this->actingAs($this->ada->user)->post(route('account.feedback.rate', $this->sunday), ['rating' => 2, 'could_improve' => ['sound'], 'comment' => 'Could not hear the sermon at the back', 'anonymous' => '1']);
        $bola = Member::factory()->active()->create(['name' => 'Bola Ade']);
        $this->actingAs($bola->user)->post(route('account.feedback.rate', $this->sunday), ['rating' => 5, 'went_well' => ['worship'], 'comment' => 'Worship was glorious']);

        $office = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($office)->get(route('admin.feedback.index'))->assertOk()->assertSee('3.5 out of 5 from 2 members');
        $this->get(route('admin.feedback.service', $this->sunday))->assertOk()
            ->assertSee('Could not hear the sermon at the back')->assertSee('A member (no name)')->assertDontSee('Ada Obi')
            ->assertSee('Bola Ade')->assertSee('1 could be better');

        $this->actingAs(User::factory()->create(['role' => 'media']))->get(route('admin.feedback.index'))->assertForbidden();
    }

    public function test_suggestions_are_answered_and_the_member_sees_the_answer(): void
    {
        $this->actingAs($this->ada->user)->post(route('account.feedback.suggest'), [
            'category' => 'services', 'subject' => 'A class for new believers', 'body' => 'Many of us joined this year and would love a foundations class.',
        ])->assertSessionHasNoErrors();
        $s = Suggestion::sole();
        $this->assertSame('new', $s->status);

        $pastor = User::factory()->create(['role' => 'pastor']);
        $this->actingAs($pastor)->get(route('admin.feedback.index', ['tab' => 'suggestions']))->assertOk()->assertSee('A class for new believers')->assertSee('Ada Obi');
        $this->post(route('admin.feedback.respond', $s), ['status' => 'planned', 'response' => 'Great idea! Starting in January.'])->assertSessionHasNoErrors();
        $this->assertSame(['planned', $pastor->id], [$s->fresh()->status, $s->fresh()->responded_by]);
        Mail::assertSent(SuggestionAnswered::class, fn ($m) => $m->hasTo($this->ada->user->email));

        // Changing only the status doesn't email again.
        $this->post(route('admin.feedback.respond', $s), ['status' => 'done', 'response' => 'Great idea! Starting in January.']);
        Mail::assertSentCount(1);

        $this->actingAs($this->ada->user)->get(route('account.feedback'))->assertSee('Starting in January.')->assertSee('Done');

        // Anonymous suggestions hide the name from staff; members not yet approved can't use the page.
        $this->post(route('account.feedback.suggest'), ['category' => 'facilities', 'subject' => 'Fans at the back', 'body' => 'It gets hot.', 'anonymous' => '1']);
        $this->actingAs($pastor)->get(route('admin.feedback.index', ['tab' => 'suggestions']))->assertSee('Fans at the back')->assertSee('A member (no name)');
        $pending = Member::factory()->create(['status' => Member::PENDING]);
        $this->actingAs($pending->user)->get(route('account.feedback'))->assertNotFound();
    }
}

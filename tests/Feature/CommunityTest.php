<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\CommunityRead;
use App\Models\CommunityReport;
use App\Models\CommunityRoom;
use App\Models\Member;
use App\Models\PushSubscription;
use App\Models\Quiz;
use App\Models\Unit;
use App\Models\User;
use App\Services\Messaging\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): User
    {
        return Member::factory()->active()->create($attributes)->user;
    }

    private function general(): CommunityRoom
    {
        return CommunityRoom::firstWhere('slug', 'general');
    }

    private function say(User $user, array $data, ?CommunityRoom $room = null)
    {
        return $this->actingAs($user)->postJson(route('community.messages.store', $room ?? $this->general()), $data);
    }

    public function test_only_approved_members_and_staff_get_in(): void
    {
        $this->get('/community')->assertRedirect(route('login'));
        $this->actingAs(Member::factory()->create()->user)->get('/community')->assertForbidden(); // pending
        $this->actingAs(User::factory()->create())->get('/community')->assertForbidden(); // no member record

        $this->actingAs($this->member())->get('/community')->assertOk()->assertSee('General')->assertSee('HOUSE RULES');
        $this->get('/')->assertSee(route('community.index'), false);
        $this->actingAs(User::factory()->staff('usher')->create())->get('/community')->assertOk();
    }

    public function test_members_chat_reply_and_see_new_messages(): void
    {
        $ada = $this->member(['name' => 'Ada Obi']);
        $tolu = $this->member(['name' => 'Tolu Ade']);

        $first = $this->say($ada, ['body' => "Hello Tribe!\nhttps://example.com/a <script>x</script>"])->assertCreated()->json('id');
        $html = $this->say($ada, ['body' => ''])->assertUnprocessable();

        $since = now()->subMinute()->toIso8601String();
        $this->say($tolu, ['body' => 'Welcome!', 'reply_to_id' => $first])->assertCreated()
            ->assertJsonPath('html', fn ($h) => str_contains($h, 'msg__reply') && str_contains($h, 'Ada Obi'));

        $this->actingAs($ada)->get(route('community.room', $this->general()))->assertOk()
            ->assertSee('<a href="https://example.com/a" target="_blank" rel="noopener nofollow ugc">', false)
            ->assertDontSee('<script>x</script>', false)
            ->assertSee('Welcome!');

        $updates = $this->getJson(route('community.updates', $this->general()).'?since='.urlencode($since).'&after=0&visible=1')->assertOk();
        $this->assertCount(2, $updates->json('messages'));
    }

    public function test_unread_counts_and_reading(): void
    {
        $ada = $this->member();
        $tolu = $this->member();
        $this->say($ada, ['body' => 'One']);
        $this->say($ada, ['body' => 'Two']);

        $this->actingAs($tolu)->get('/community')->assertSee('unread: </span>2', false);
        $this->get(route('community.room', $this->general()));
        $this->assertSame(CommunityMessage::max('id'), CommunityRead::where('user_id', $tolu->id)->value('last_read_id'));
        $this->get('/community')->assertDontSee('unread: </span>', false);
    }

    public function test_photos_polls_reactions_and_votes(): void
    {
        Storage::fake('public');
        $ada = $this->member();
        $tolu = $this->member();

        $photo = $this->say($ada, ['image' => UploadedFile::fake()->image('choir.jpg', 2000, 1500)])->assertCreated()->json('id');
        Storage::disk('public')->assertExists(CommunityMessage::find($photo)->image_path);

        $this->say($ada, ['poll_question' => 'Best worship song?', 'poll_options' => ['Way Maker', '']])->assertUnprocessable();
        $poll = $this->say($ada, ['poll_question' => 'Best worship song?', 'poll_options' => ['Way Maker', 'Goodness of God', 'Way Maker'], 'poll_hours' => 24])->json('id');
        $this->assertSame(['Way Maker', 'Goodness of God'], CommunityMessage::find($poll)->poll['options']);

        $message = CommunityMessage::find($poll);
        $this->actingAs($tolu)->postJson(route('community.messages.vote', $message), ['option' => 1])->assertOk();
        $this->postJson(route('community.messages.vote', $message), ['option' => 0])->assertOk(); // changes the vote
        $this->postJson(route('community.messages.vote', $message), ['option' => 5])->assertUnprocessable();
        $this->assertSame([1, 0], $message->fresh()->load('votes')->pollCounts());

        $this->postJson(route('community.messages.react', $message), ['emoji' => '🔥'])->assertOk()->assertJsonPath('html', fn ($h) => str_contains($h, 'reaction--mine'));
        $this->postJson(route('community.messages.react', $message), ['emoji' => '🔥'])->assertOk(); // toggles off
        $this->postJson(route('community.messages.react', $message), ['emoji' => '💩'])->assertUnprocessable();
        $this->assertSame(0, $message->reactions()->count());

        $this->travel(2)->days();
        $this->postJson(route('community.messages.vote', $message), ['option' => 1])->assertUnprocessable();
    }

    public function test_editing_window_and_removing(): void
    {
        $ada = $this->member();
        $tolu = $this->member();
        $id = $this->say($ada, ['body' => 'Typo hre'])->json('id');
        $message = CommunityMessage::find($id);

        $this->actingAs($tolu)->putJson(route('community.messages.update', $message), ['body' => 'Hacked'])->assertForbidden();
        $this->deleteJson(route('community.messages.destroy', $message))->assertForbidden();

        $this->actingAs($ada)->putJson(route('community.messages.update', $message), ['body' => 'Typo here'])->assertOk();
        $this->assertNotNull($message->fresh()->edited_at);
        $this->travel(20)->minutes();
        $this->putJson(route('community.messages.update', $message), ['body' => 'Too late'])->assertForbidden();

        $this->deleteJson(route('community.messages.destroy', $message))->assertOk()->assertJsonPath('html', fn ($h) => str_contains($h, 'Message removed'));
        $this->assertNull($message->fresh()->body);
    }

    public function test_reports_moderation_and_muting(): void
    {
        $ada = $this->member(['name' => 'Ada Obi']);
        $tolu = $this->member();
        $id = $this->say($ada, ['body' => 'Something rude'])->json('id');
        $message = CommunityMessage::find($id);

        $this->actingAs($ada)->postJson(route('community.messages.report', $message), ['reason' => 'self'])->assertNotFound();
        $this->actingAs($tolu)->postJson(route('community.messages.report', $message), ['reason' => 'Rude'])->assertOk();
        $this->postJson(route('community.messages.report', $message), ['reason' => 'Rude again'])->assertOk();
        $this->assertSame(1, CommunityReport::count());

        $moderator = User::factory()->staff('secretary')->create();
        $this->actingAs($moderator)->get(route('admin.community.index'))->assertOk()->assertSee('Something rude')->assertSee('"Rude"');
        $this->post(route('admin.community.reports.resolve', CommunityReport::first()), ['outcome' => 'muted', 'days' => 7, 'reason' => 'Rude to members'])->assertRedirect();

        $this->assertNotNull($message->fresh()->removed_at);
        $this->assertTrue($ada->fresh()->isMutedInCommunity());
        $this->say($ada->fresh(), ['body' => 'Let me in'])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAs($ada->fresh())->get(route('community.room', $this->general()))->assertSee('Rude to members');

        $this->actingAs($moderator)->delete(route('admin.community.unmute', $ada))->assertRedirect();
        $this->say($ada->fresh(), ['body' => 'Sorry everyone'])->assertCreated();

        // Members can't reach moderation.
        $this->actingAs($tolu)->get(route('admin.community.index'))->assertForbidden();
    }

    public function test_unit_rooms_and_locked_rooms(): void
    {
        $choir = Unit::factory()->create(['name' => 'Choir']);
        $room = CommunityRoom::create(['name' => 'Choir room', 'slug' => 'choir-room', 'unit_id' => $choir->id]);
        $singer = $this->member(['unit_id' => $choir->id]);
        $other = $this->member();

        $this->actingAs($singer)->get('/community')->assertSee('Choir room');
        $this->actingAs($other)->get('/community')->assertDontSee('Choir room');
        $this->get(route('community.room', $room))->assertNotFound();
        $this->say($other, ['body' => 'Hi'], $room)->assertNotFound();

        $this->general()->update(['is_locked' => true]);
        $this->say($singer, ['body' => 'Hi'])->assertUnprocessable();
        $this->say(User::factory()->admin()->create(), ['body' => 'Notice'])->assertCreated();
    }

    public function test_replies_notify_the_writer_on_their_phone(): void
    {
        $ada = $this->member();
        $tolu = $this->member(['name' => 'Tolu Ade']);
        PushSubscription::create(['user_id' => $ada->id, 'endpoint' => 'https://fcm.googleapis.com/x', 'endpoint_hash' => hash('sha256', 'x'), 'public_key' => 'k', 'auth_token' => 'a']);
        $this->mock(WebPush::class, function ($mock) use ($ada) {
            $mock->shouldReceive('configured')->andReturnTrue();
            $mock->shouldReceive('send')->once()->withArgs(fn ($subs, $n) => $subs->first()->user_id === $ada->id && str_contains($n['title'], 'Tolu Ade replied'))
                ->andReturn(['sent' => 1, 'failed' => 0]);
        });
        $first = $this->say($ada, ['body' => 'Who is coming?'])->json('id');

        $this->say($tolu, ['body' => 'Me!', 'reply_to_id' => $first])->assertCreated();
        $this->say($ada, ['body' => 'Replying to myself', 'reply_to_id' => $first])->assertCreated(); // no notification
    }

    public function test_birthdays_this_week_show_without_the_year(): void
    {
        $this->member(['name' => 'Birthday Girl', 'birthday' => today()->subYears(21)]);
        $this->member(['name' => 'Next Month', 'birthday' => today()->addMonth()->subYears(30)]);

        $this->actingAs($this->member())->get('/community')->assertSee('Birthday Girl')->assertSee('Today!')->assertDontSee('Next Month');
    }

    public function test_quiz_is_played_once_timed_and_scored_on_the_server(): void
    {
        $quiz = Quiz::create(['title' => 'Heroes of faith', 'opens_at' => now()->subHour(), 'closes_at' => now()->addDay(), 'seconds_per_question' => 20, 'is_published' => true]);
        $q1 = $quiz->questions()->create(['question' => 'Who built the ark?', 'options' => ['Moses', 'Noah'], 'answer' => 1]);
        $q2 = $quiz->questions()->create(['question' => 'Who killed Goliath?', 'options' => ['David', 'Saul'], 'answer' => 0, 'reference' => '1 Samuel 17']);
        $ada = $this->member();

        $this->actingAs($ada)->get('/community')->assertSee('Heroes of faith')->assertSee('Play now');
        $this->get(route('community.quiz', $quiz))->assertSee('Start the quiz')->assertDontSee('Who built the ark?');
        $this->post(route('community.quiz.start', $quiz))->assertRedirect();
        $this->get(route('community.quiz', $quiz))->assertSee('Who built the ark?')->assertDontSee('"answer"', false);

        $this->post(route('community.quiz.submit', $quiz), ['answers' => [$q1->id => 1, $q2->id => 1]])->assertSessionHas('status', 'You scored 1 out of 2.');
        // One go only.
        $this->post(route('community.quiz.submit', $quiz), ['answers' => [$q1->id => 1, $q2->id => 0]]);
        $this->assertSame(1, $quiz->attempts()->first()->score);
        $this->get(route('community.quiz', $quiz))->assertSee('Your score: 1/2')->assertDontSee('1 Samuel 17');

        // Too slow doesn't count.
        $tolu = $this->member();
        $this->actingAs($tolu)->post(route('community.quiz.start', $quiz));
        $this->travel(5)->minutes();
        $this->post(route('community.quiz.submit', $quiz), ['answers' => [$q1->id => 1, $q2->id => 0]]);
        $this->assertSame(0, $quiz->attempts()->where('user_id', $tolu->id)->value('score'));

        // Answers show once it closes.
        $this->travel(2)->days();
        $this->actingAs($ada)->get(route('community.quiz', $quiz))->assertSee('1 Samuel 17')->assertSee('You said: Saul');
    }

    public function test_staff_build_quizzes_and_rooms(): void
    {
        $this->actingAs(User::factory()->staff('media')->create());

        $this->post(route('admin.community.quizzes.store'), [
            'title' => 'Parables', 'opens_at' => now()->format('Y-m-d\TH:i'), 'closes_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'seconds_per_question' => 15, 'is_published' => '1',
            'questions' => [
                ['question' => 'Lost sheep: how many were safe?', 'options' => ['', '99', '100', ''], 'answer' => 1],
                ['question' => '', 'options' => []],
            ],
        ])->assertRedirect();
        $question = Quiz::firstWhere('title', 'Parables')->questions()->first();
        $this->assertSame(['99', '100'], $question->options);
        $this->assertSame(0, $question->answer);

        $this->post(route('admin.community.quizzes.store'), [
            'title' => 'Bad', 'opens_at' => now()->format('Y-m-d\TH:i'), 'closes_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'seconds_per_question' => 15,
            'questions' => [['question' => 'No answer ticked', 'options' => ['A', 'B']]],
        ])->assertSessionHasErrors('questions.0.answer');

        $this->post(route('admin.community.rooms.store'), ['name' => 'Quiz'])->assertRedirect();
        $this->assertTrue(CommunityRoom::where('slug', 'quiz-room')->exists());
    }
}

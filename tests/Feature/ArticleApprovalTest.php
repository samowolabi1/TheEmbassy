<?php

namespace Tests\Feature;

use App\Mail\ReminderMail;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ArticleApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $writer;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->writer = User::factory()->staff('writer')->create(['name' => 'Bro. Tayo']);
        $this->editor = User::factory()->staff('web')->create(['email' => 'web@example.com']);
    }

    private function article(array $overrides = []): array
    {
        return ['title' => 'Sowing seeds at the office', 'topic' => 'Faith', 'swatch' => Post::SWATCHES[0], 'dek' => 'Living it out on Monday.', 'body' => "## Monday\n\nGod goes with you.", ...$overrides];
    }

    public function test_a_writer_sends_an_article_for_approval_and_cannot_publish_it(): void
    {
        $this->actingAs($this->writer)->get(route('admin.posts.create'))->assertSee('Send for approval')->assertDontSee('value="published"', false)->assertDontSee('Feature at the top');

        $this->post(route('admin.posts.store'), $this->article(['status' => 'published']))->assertSessionHasErrors('status');
        $this->post(route('admin.posts.store'), $this->article(['status' => 'review', 'is_featured' => '1']))->assertSessionHasNoErrors();

        $post = Post::firstWhere('title', 'Sowing seeds at the office');
        $this->assertSame(['pending', null, false], [$post->review, $post->published_at, $post->is_featured]);
        session()->forget('status'); // The "sent" message would pop up on the next page.
        $this->get(route('blog.index'))->assertDontSee('Sowing seeds at the office');
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo('web@example.com') && str_contains($m->body, 'Bro. Tayo sent "Sowing seeds at the office"'));

        // Editors see it waiting, with a count on the menu.
        $this->actingAs($this->editor)->get(route('admin.posts.index', ['show' => 'waiting']))->assertSee('Sowing seeds at the office')->assertSee('Waiting for approval');
        $this->get(route('admin.posts.edit', $post))->assertSee('Waiting for your approval')->assertSee('Approve and publish');
    }

    public function test_the_editor_approves_and_the_writer_hears(): void
    {
        $this->actingAs($this->writer)->post(route('admin.posts.store'), $this->article(['status' => 'review']));
        $post = Post::firstWhere('title', 'Sowing seeds at the office');

        $this->actingAs($this->editor)->post(route('admin.posts.approve', $post))->assertSessionHasNoErrors();
        $this->assertTrue($post->fresh()->isLive());
        $this->assertNull($post->fresh()->review);
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($this->writer->email) && str_contains($m->body, 'is approved and live'));
        $this->get(route('blog.show', $post->fresh()->slug))->assertOk()->assertSee('Sowing seeds at the office');

        // Once live, the writer can't change it on their own.
        $this->actingAs($this->writer)->get(route('admin.posts.edit', $post))->assertForbidden();
        $this->put(route('admin.posts.update', $post), $this->article(['title' => 'Changed', 'status' => 'review']))->assertForbidden();
        $this->delete(route('admin.posts.destroy', $post))->assertForbidden();
    }

    public function test_a_chosen_time_is_kept_when_approved(): void
    {
        $at = now()->addDays(3)->setTime(7, 0);
        $this->actingAs($this->writer)->post(route('admin.posts.store'), $this->article(['status' => 'review', 'publish_at' => $at->format('Y-m-d\TH:i')]));
        $post = Post::firstWhere('title', 'Sowing seeds at the office');

        $this->actingAs($this->editor)->post(route('admin.posts.approve', $post));
        $this->assertSame('scheduled', $post->fresh()->status());
        $this->assertTrue($post->fresh()->published_at->equalTo($at));
    }

    public function test_the_editor_sends_it_back_and_the_writer_resends(): void
    {
        $this->actingAs($this->writer)->post(route('admin.posts.store'), $this->article(['status' => 'review']));
        $post = Post::firstWhere('title', 'Sowing seeds at the office');

        $this->actingAs($this->editor)->post(route('admin.posts.send-back', $post), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('admin.posts.send-back', $post), ['note' => 'Please add the scripture reference.'])->assertSessionHasNoErrors();
        $this->assertSame('changes', $post->fresh()->review);
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($this->writer->email) && str_contains($m->body, 'Please add the scripture reference.'));

        $this->actingAs($this->writer)->get(route('admin.posts.edit', $post))->assertSee('Please make a few changes')->assertSee('Please add the scripture reference.');
        $this->put(route('admin.posts.update', $post), $this->article(['body' => 'Fixed — James 2:17', 'status' => 'review']))->assertSessionHasNoErrors();
        $this->assertSame('pending', $post->fresh()->review);
    }

    public function test_an_editor_publishing_from_the_editor_also_approves_it(): void
    {
        $this->actingAs($this->writer)->post(route('admin.posts.store'), $this->article(['status' => 'review']));
        $post = Post::firstWhere('title', 'Sowing seeds at the office');

        $this->actingAs($this->editor)->put(route('admin.posts.update', $post), $this->article(['title' => 'Sowing seeds at the office (edited)', 'status' => 'published']))->assertSessionHasNoErrors();
        $post = Post::firstWhere('title', 'Sowing seeds at the office (edited)');
        $this->assertTrue($post->isLive());
        $this->assertNull($post->review);
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($this->writer->email) && str_contains($m->body, 'approved'));
    }

    public function test_writers_only_see_and_open_their_own_articles(): void
    {
        $other = Post::factory()->create(['title' => 'Someone else\'s draft', 'published_at' => null]);
        $this->actingAs($this->writer)->get(route('admin.posts.index'))->assertDontSee('Someone else');
        $this->get(route('admin.posts.edit', $other))->assertForbidden();
        $this->get(route('admin.posts.preview', $other))->assertForbidden();
        $this->post(route('admin.posts.approve', $other))->assertForbidden();
    }

    public function test_editors_still_publish_their_own_articles_directly(): void
    {
        $this->actingAs($this->editor)->post(route('admin.posts.store'), $this->article(['status' => 'published']))->assertSessionHasNoErrors();
        $this->assertTrue(Post::firstWhere('title', 'Sowing seeds at the office')->isLive());
        Mail::assertNothingSent();
    }
}

<?php

namespace Tests\Feature;

use App\Mail\ConfirmNewsletter;
use App\Mail\WeeklyNewsletter;
use App\Models\Announcement;
use App\Models\Declaration;
use App\Models\NewsletterIssue;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\User;
use App\Services\Messaging\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_subscribers_confirm_by_email_first(): void
    {
        Mail::fake();

        $this->from('/')->post('/newsletter', ['email' => 'Ada@Example.com'])->assertSessionHas('subscribed');
        $subscriber = NewsletterSubscriber::firstWhere('email', 'ada@example.com');
        $this->assertFalse($subscriber->isReceiving());
        Mail::assertSent(ConfirmNewsletter::class, fn ($m) => $m->hasTo('ada@example.com'));

        // Signing up again straight away doesn't send another email.
        $this->from('/')->post('/newsletter', ['email' => 'ada@example.com']);
        Mail::assertSentCount(1);

        $this->get($subscriber->confirmUrl())->assertOk()->assertSee('See you on Monday.');
        $this->assertTrue($subscriber->fresh()->isReceiving());
    }

    public function test_unsubscribing_asks_first_and_supports_one_click(): void
    {
        $subscriber = NewsletterSubscriber::create(['email' => 'ada@example.com', 'confirmed_at' => now()]);

        $this->get($subscriber->unsubscribeUrl())->assertOk()->assertSee('Stop the weekly email?');
        $this->assertTrue($subscriber->fresh()->isReceiving());

        // Mail apps post without a CSRF token.
        $this->post($subscriber->unsubscribeUrl(), ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertFalse($subscriber->fresh()->isReceiving());

        $this->get('/newsletter/unsubscribe/'.str_repeat('x', 40))->assertNotFound();
    }

    public function test_the_week_is_queued_once_and_sent_in_batches_to_confirmed_subscribers(): void
    {
        Mail::fake();
        Declaration::create(['week_of' => today()->startOfWeek(), 'lines' => ['I am chosen.', '', '', '']]);
        Announcement::factory()->create(['title' => 'Choir auditions', 'published_at' => now()->subDay()]);
        NewsletterSubscriber::create(['email' => 'a@example.com', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'b@example.com', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'c@example.com', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'pending@example.com']);
        NewsletterSubscriber::create(['email' => 'gone@example.com', 'confirmed_at' => now(), 'unsubscribed_at' => now()]);
        $newsletter = app(Newsletter::class);

        $this->artisan('newsletter:queue')->assertSuccessful();
        $this->artisan('newsletter:queue')->expectsOutputToContain('already gone out');
        $this->assertSame(1, NewsletterIssue::count());

        $this->assertSame(2, $newsletter->sendBatch(2));
        $this->assertSame('sending', NewsletterIssue::first()->status);
        $this->assertSame(1, $newsletter->sendBatch(2));
        $this->assertSame(0, $newsletter->sendBatch(2));

        $issue = NewsletterIssue::first();
        $this->assertSame('sent', $issue->status);
        $this->assertSame(3, $issue->sent_count);
        Mail::assertSentCount(3);
        Mail::assertNotSent(WeeklyNewsletter::class, fn ($m) => $m->hasTo('pending@example.com') || $m->hasTo('gone@example.com'));
        Mail::assertSent(WeeklyNewsletter::class, function (WeeklyNewsletter $m) {
            $html = $m->render();

            return $m->hasTo('a@example.com')
                && str_contains($html, 'I am chosen.')
                && str_contains($html, 'Choir auditions')
                && str_contains($html, '/newsletter/unsubscribe/')
                && str_contains($m->headers()->text['List-Unsubscribe'], '/newsletter/unsubscribe/');
        });
    }

    public function test_staff_preview_and_send_now(): void
    {
        Mail::fake();
        Post::query()->delete(); // the starter articles
        Post::factory()->create(['title' => 'Fresh article', 'published_at' => now()->subDay()]);
        NewsletterSubscriber::create(['email' => 'a@example.com', 'confirmed_at' => now()]);
        $this->actingAs(User::factory()->staff('secretary')->create());

        $this->get(route('admin.subscribers.preview'))->assertOk()->assertSee('Fresh article');
        $this->get(route('admin.subscribers.index'))->assertOk()->assertSee('Not sent yet');

        $this->post(route('admin.subscribers.send'))->assertSessionHas('status', fn ($s) => str_contains($s, 'Sending'));
        $this->post(route('admin.subscribers.send'))->assertSessionHas('status', fn ($s) => str_contains($s, 'already'));
        $this->artisan('messages:send')->assertSuccessful();

        Mail::assertSent(WeeklyNewsletter::class, 1);
        $this->get(route('admin.subscribers.index'))->assertSee('Sent');
    }

    public function test_a_failed_address_does_not_stop_the_rest(): void
    {
        NewsletterSubscriber::create(['email' => 'a@example.com', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'b@example.com', 'confirmed_at' => now()]);
        Mail::shouldReceive('to')->twice()->andReturnUsing(function ($to) {
            $pending = \Mockery::mock();
            $pending->shouldReceive('send')->andReturnUsing(fn () => $to === 'a@example.com' ? throw new \RuntimeException('bounce') : null);

            return $pending;
        });

        app(Newsletter::class)->queue();
        app(Newsletter::class)->sendBatch();

        $issue = NewsletterIssue::first();
        $this->assertSame([1, 1, 'sent'], [$issue->sent_count, $issue->failed_count, $issue->status]);
    }
}

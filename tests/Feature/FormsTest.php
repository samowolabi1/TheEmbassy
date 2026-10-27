<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\PrayerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prayer_request_is_saved(): void
    {
        $this->post('/prayer-requests', [
            'name' => 'Tolu',
            'contact' => 'tolu@example.com',
            'request' => 'Please pray for my exams.',
            'is_private' => '1',
        ])->assertRedirect(route('home').'#prayer');

        $request = PrayerRequest::sole();
        $this->assertSame('Tolu', $request->name);
        $this->assertSame('Please pray for my exams.', $request->request);
        $this->assertTrue($request->is_private);
    }

    public function test_prayer_request_shows_confirmation(): void
    {
        $this->followingRedirects()
            ->post('/prayer-requests', ['request' => 'Pray for my family.'])
            ->assertSee("We've received it. We're praying with you.", false);
    }

    public function test_prayer_request_needs_a_request(): void
    {
        $this->post('/prayer-requests', ['name' => 'Tolu'])
            ->assertRedirect(route('home').'#prayer')
            ->assertSessionHasErrors('request');

        $this->assertDatabaseCount('prayer_requests', 0);
    }

    public function test_prayer_request_honeypot_is_ignored(): void
    {
        $this->post('/prayer-requests', ['request' => 'Buy now', 'website' => 'http://spam.example'])
            ->assertRedirect(route('home').'#prayer');

        $this->assertDatabaseCount('prayer_requests', 0);
    }

    public function test_newsletter_signup_is_saved_once(): void
    {
        $this->from('/about')->post('/newsletter', ['email' => 'Ada@Example.com'])
            ->assertRedirect(url('/about').'#newsletter')
            ->assertSessionHas('subscribed');
        $this->from('/about')->post('/newsletter', ['email' => 'ada@example.com']);

        $this->assertSame(['ada@example.com'], NewsletterSubscriber::pluck('email')->all());
    }

    public function test_newsletter_needs_a_valid_email(): void
    {
        $this->from('/')->post('/newsletter', ['email' => 'not-an-email'])
            ->assertSessionHasErrorsIn('newsletter', 'email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_newsletter_redirect_stays_on_this_site(): void
    {
        $this->withHeader('referer', 'https://evil.example/phish')
            ->post('/newsletter', ['email' => 'ada@example.com'])
            ->assertRedirect(url('/phish').'#newsletter');
    }
}

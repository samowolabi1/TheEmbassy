<?php

namespace Tests\Feature;

use App\Mail\ReminderMail;
use App\Models\Announcement;
use App\Models\Broadcast;
use App\Models\Member;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Services\Messaging\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppAndBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function whatsapp(): void
    {
        config([
            'church.notify.whatsapp' => '1',
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.template' => 'church_notice',
            'services.whatsapp.language' => 'en',
        ]);
    }

    private function subscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): PushSubscription
    {
        return PushSubscription::create([
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => 'BKey',
            'auth_token' => 'auth',
        ]);
    }

    public function test_manifest_icons_service_worker_and_offline_page(): void
    {
        $this->get('/manifest.webmanifest')->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('icons.1.sizes', '512x512');

        $icon = $this->get('/app-icon-192.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame([192, 192], array_slice(getimagesizefromstring($icon->getContent()), 0, 2));
        $this->get('/app-icon-100.png')->assertNotFound();

        $this->get('/sw.js')->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertSee("addEventListener('push'", false);
        $this->get('/offline')->assertOk()->assertSee("You're offline.", false);
        $this->get('/')->assertOk()->assertSee('rel="manifest"', false)->assertSee('data-install', false);
    }

    public function test_phones_subscribe_to_notifications_only_on_real_push_services(): void
    {
        $user = User::factory()->create();
        $payload = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz', 'keys' => ['p256dh' => 'BPub', 'auth' => 'secret'], 'contentEncoding' => 'aes128gcm'];

        $this->actingAs($user)->postJson('/push/subscribe', $payload)->assertOk();
        $this->postJson('/push/subscribe', $payload)->assertOk(); // same phone again: no duplicate
        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($user->id, PushSubscription::first()->user_id);

        $this->postJson('/push/subscribe', [...$payload, 'endpoint' => 'https://169.254.169.254/latest'])->assertStatus(422);
        $this->postJson('/push/subscribe', [...$payload, 'endpoint' => 'http://fcm.googleapis.com/x'])->assertStatus(422);
        $this->postJson('/push/subscribe', [...$payload, 'endpoint' => 'https://fcm.googleapis.com.evil.test/x'])->assertStatus(422);

        $this->postJson('/push/unsubscribe', ['endpoint' => $payload['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_push_keys_are_created_once_and_stored_encrypted(): void
    {
        $this->artisan('push:keys')->assertSuccessful();
        Setting::applyToConfig();
        $public = config('services.webpush.public_key');

        $this->assertNotEmpty($public);
        $this->assertNotSame(config('services.webpush.private_key'), Setting::firstWhere('key', 'services.webpush.private_key')->value);
        $this->artisan('push:keys')->expectsOutputToContain('already exist');
        $this->assertSame($public, config('services.webpush.public_key'));
        $this->get('/')->assertOk()->assertSee('name="push-key"', false);
    }

    public function test_announcements_go_by_email_and_whatsapp(): void
    {
        Mail::fake();
        $this->whatsapp();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        Member::factory()->active()->create(['phone' => '0803 123 4567']);
        Member::factory()->active()->create(['phone' => '+234 803 123 4567']); // same number: one WhatsApp
        Member::factory()->active()->create(['phone' => '08055555555', 'notify_sms' => false]); // turned WhatsApp off
        Member::factory()->create(['phone' => '08066666666']); // pending
        Member::factory()->active()->create(['phone' => '12']); // not a number
        $announcement = Announcement::factory()->create(['title' => 'Choir auditions', 'published_at' => now()->subHour()]);
        $this->actingAs(User::factory()->staff('media')->create());

        $this->get(route('admin.announcements.send', $announcement))->assertOk()->assertSee('Email to 4 members')->assertSee('WhatsApp to 3 members')
            ->assertDontSee('Text message')->assertSee('value="email" checked', false);
        $this->post(route('admin.announcements.send.store', $announcement), ['channels' => ['email', 'whatsapp'], 'message' => 'Choir auditions this Saturday.'])->assertRedirect();
        $this->artisan('messages:send')->assertSuccessful();

        $broadcast = Broadcast::first();
        $this->assertSame(['sent', 4, 1, 2], [$broadcast->status, $broadcast->email_sent, $broadcast->whatsapp_sent, $broadcast->whatsapp_failed]);
        Mail::assertSent(ReminderMail::class, 4);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v21.0/1234567890/messages' && $r['to'] === '2348031234567'
            && $r['template']['name'] === 'church_notice' && str_contains($r['template']['components'][0]['parameters'][0]['text'], 'Choir auditions this Saturday.')
            && $r->hasHeader('Authorization', 'Bearer test-token'));

        // A second send within a day needs confirming.
        $this->post(route('admin.announcements.send.store', $announcement), ['channels' => ['email'], 'message' => 'Again'])->assertSessionHasErrors('again');
    }

    public function test_sending_needs_a_way_that_is_switched_on(): void
    {
        $announcement = Announcement::factory()->create(['published_at' => now()->subHour()]);
        $this->actingAs(User::factory()->admin()->create());

        // WhatsApp isn't set up; text messages aren't offered at all.
        $this->get(route('admin.announcements.send', $announcement))->assertOk()->assertSee('Off or not set up');
        $this->post(route('admin.announcements.send.store', $announcement), ['channels' => ['whatsapp'], 'message' => 'Hi'])->assertSessionHasErrors('channels.0');
        $this->post(route('admin.announcements.send.store', $announcement), ['channels' => ['sms'], 'message' => 'Hi'])->assertSessionHasErrors('channels.0');
        config(['church.notify.email' => '0']);
        $this->post(route('admin.announcements.send.store', $announcement), ['channels' => ['email'], 'message' => 'Hi'])->assertSessionHasErrors('channels.0');
        $this->assertSame(0, Broadcast::count());

        $this->actingAs(User::factory()->staff('finance')->create());
        $this->get(route('admin.announcements.send', $announcement))->assertForbidden();
    }

    public function test_the_email_switch_stops_notification_emails_but_not_receipts(): void
    {
        config(['mail.default' => 'array']);
        $transport = fn () => app('mailer')->getSymfonyTransport();
        Mail::to('ada@example.com')->send(new ReminderMail('Sunday service today', 'See you at 9.'));
        $this->assertCount(1, $transport()->messages());

        config(['church.notify.email' => '0']);
        Mail::to('ada@example.com')->send(new ReminderMail('Sunday service today', 'See you at 9.'));
        Mail::raw('Your receipt', fn ($m) => $m->to('ada@example.com')->subject('Receipt'));
        $this->assertCount(2, $transport()->messages());
        $this->assertSame('Receipt', $transport()->messages()->last()->getOriginalMessage()->getSubject());
    }

    public function test_the_app_notification_switch_stops_push(): void
    {
        config(['church.notify.push' => '0', 'services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(WebPush::class)->send(collect([$this->subscription()]), ['title' => 'T', 'body' => 'B']));
    }

    public function test_push_notifications_go_to_every_subscription(): void
    {
        $this->subscription('https://fcm.googleapis.com/fcm/send/1');
        $this->subscription('https://web.push.apple.com/2');
        $this->mock(WebPush::class, function ($mock) {
            $mock->shouldReceive('configured')->andReturnTrue();
            $mock->shouldReceive('send')->once()->withArgs(fn ($subs, $n) => $subs->count() === 2 && $n['title'] === 'Choir' && $n['body'] === 'Saturday')
                ->andReturn(['sent' => 2, 'failed' => 0]);
        });
        Broadcast::create(['title' => 'Choir', 'message' => 'Saturday', 'url' => 'https://example.com', 'channels' => ['push'], 'status' => 'queued']);

        $this->artisan('messages:send')->assertSuccessful();

        $this->assertSame([2, 'sent'], [Broadcast::first()->push_sent, Broadcast::first()->status]);
    }

    public function test_members_turn_whatsapp_off_in_their_account(): void
    {
        $member = Member::factory()->active()->create(['phone' => '08011111111']);
        $this->actingAs($member->user);

        $this->get('/account')->assertOk()->assertSee('Send me church messages on WhatsApp')->assertSee('data-push-toggle', false);
        $this->put('/account', ['phone' => '08011111111'])->assertRedirect();

        $this->assertFalse($member->fresh()->notify_sms);
    }

    public function test_unused_article_pictures_are_pruned(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        foreach (['used', 'unused', 'fresh'] as $name) {
            $disk->put("blog/inline/2026/10/{$name}.webp", 'x');
            $disk->put("blog/inline/2026/10/{$name}-thumb.webp", 'x');
        }
        touch($disk->path('blog/inline/2026/10/used.webp'), now()->subDays(5)->getTimestamp());
        touch($disk->path('blog/inline/2026/10/unused.webp'), now()->subDays(5)->getTimestamp());
        Post::factory()->create(['body' => 'Look ![x](/storage/blog/inline/2026/10/used.webp)']);

        $this->artisan('blog:prune-images')->expectsOutputToContain('Removed 1');

        $disk->assertExists(['blog/inline/2026/10/used.webp', 'blog/inline/2026/10/fresh.webp']);
        $disk->assertMissing(['blog/inline/2026/10/unused.webp', 'blog/inline/2026/10/unused-thumb.webp']);
    }

    public function test_app_wording_points_to_real_features(): void
    {
        $this->get('/about')->assertOk()->assertSee('Join a unit')->assertDontSee('Join a unit in the app');
        $this->get('/')->assertOk()->assertDontSee('RSVP in the app')->assertDontSee('App Store');
    }
}

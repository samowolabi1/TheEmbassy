<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Broadcast;
use App\Models\Member;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Services\Messaging\Broadcaster;
use App\Services\Messaging\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppAndBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function termii(string $channel = 'generic'): void
    {
        config([
            'services.termii.api_key' => 'test-key',
            'services.termii.sender_id' => 'RoyalTribe',
            'services.termii.channel' => $channel,
            'services.termii.base_url' => 'https://termii.test',
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
        $this->get('/')->assertSee('rel="manifest"', false)->assertSee('data-install', false);
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
        $this->get('/')->assertSee('name="push-key"', false);
    }

    public function test_announcements_are_texted_in_batches_to_members_who_want_texts(): void
    {
        $this->termii();
        Http::fake(['termii.test/*' => Http::response(['code' => 'ok', 'message' => 'Successfully Sent'])]);
        Member::factory()->active()->create(['phone' => '0803 123 4567']);
        Member::factory()->active()->create(['phone' => '+234 803 123 4567']); // same number: one text
        Member::factory()->active()->create(['phone' => '08055555555', 'notify_sms' => false]);
        Member::factory()->create(['phone' => '08066666666']); // pending
        Member::factory()->active()->create(['phone' => '12']); // not a number
        $announcement = Announcement::factory()->create(['title' => 'Choir auditions', 'published_at' => now()->subHour()]);
        $this->actingAs(User::factory()->staff('media')->create());

        $this->get(route('admin.announcements.send', $announcement))->assertOk()->assertSee('Text message to 3 members');
        $this->post(route('admin.announcements.send.store', $announcement), ['channels' => ['sms'], 'message' => 'Choir auditions this Saturday.'])->assertRedirect();
        $this->artisan('messages:send')->assertSuccessful();

        $broadcast = Broadcast::first();
        $this->assertSame('sent', $broadcast->status);
        $this->assertSame([1, 1], [$broadcast->sms_sent, $broadcast->sms_failed]);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://termii.test/api/sms/send/bulk'
            && $r['to'] === ['2348031234567'] && $r['from'] === 'RoyalTribe' && $r['channel'] === 'generic' && $r['sms'] === 'Choir auditions this Saturday.');

        // A second send within a day needs confirming.
        $this->post(route('admin.announcements.send.store', $announcement), ['channels' => ['sms'], 'message' => 'Again'])->assertSessionHasErrors('again');
    }

    public function test_dnd_route_texts_one_at_a_time_and_errors_stop_texting(): void
    {
        $this->termii('dnd');
        Http::fake(['termii.test/*' => Http::sequence()->push(['message' => 'ok'])->push(['message' => 'Insufficient balance'], 400)]);
        Member::factory()->active()->count(3)->sequence(['phone' => '08011111111'], ['phone' => '08022222222'], ['phone' => '08033333333'])->create();
        Broadcast::create(['title' => 'T', 'message' => 'Hello', 'channels' => ['sms'], 'status' => 'queued']);

        app(Broadcaster::class)->sendBatch();

        $broadcast = Broadcast::first();
        $this->assertSame('sent', $broadcast->status);
        $this->assertStringContainsString('Insufficient balance', $broadcast->sms_error);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://termii.test/api/sms/send' && $r['channel'] === 'dnd');
        Http::assertSentCount(2);
    }

    public function test_sending_needs_a_configured_channel(): void
    {
        $announcement = Announcement::factory()->create(['published_at' => now()->subHour()]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.announcements.send', $announcement))->assertSee('Set up Termii');
        $this->post(route('admin.announcements.send.store', $announcement), ['channels' => ['sms'], 'message' => 'Hi'])->assertSessionHasErrors('channels.0');
        $this->assertSame(0, Broadcast::count());

        $this->actingAs(User::factory()->staff('finance')->create());
        $this->get(route('admin.announcements.send', $announcement))->assertForbidden();
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

    public function test_members_turn_texts_off_in_their_account(): void
    {
        $member = Member::factory()->active()->create(['phone' => '08011111111']);
        $this->actingAs($member->user);

        $this->get('/account')->assertSee('Text me church announcements')->assertSee('data-push-toggle', false);
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
        $this->get('/about')->assertSee('Join a unit')->assertDontSee('Join a unit in the app');
        $this->get('/')->assertDontSee('RSVP in the app')->assertDontSee('App Store');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Service;
use App\Models\ServiceSetup;
use App\Models\Setting;
use App\Models\StreamDestination;
use App\Models\Unit;
use App\Models\User;
use App\Models\YouTubeConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YouTubeTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Member $hod;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google' => ['client_id' => 'client-123', 'client_secret' => 'secret-456'], 'church.youtube' => ['days_ahead' => 7, 'privacy' => 'public']]);
        $this->unit = Unit::factory()->media()->create();
        $this->hod = Member::factory()->active()->create(['unit_id' => $this->unit->id]);
        $this->unit->update(['hod_member_id' => $this->hod->id]);
    }

    private function connection(array $overrides = []): YouTubeConnection
    {
        return YouTubeConnection::create([
            'channel_id' => 'UC123', 'channel_title' => 'Royal Tribe TV', 'access_token' => 'ya29.access', 'refresh_token' => '1//refresh',
            'expires_at' => now()->addHour(), 'stream_id' => 'stream-1', ...$overrides,
        ]);
    }

    private function fakeYouTube(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.new', 'refresh_token' => '1//refresh', 'expires_in' => 3599]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UC123', 'snippet' => ['title' => 'Royal Tribe TV']]]]),
            'www.googleapis.com/youtube/v3/liveStreams*' => Http::response(['id' => 'stream-1', 'items' => [['id' => 'stream-1', 'cdn' => ['ingestionInfo' => ['ingestionAddress' => 'rtmp://a.rtmp.youtube.com/live2', 'streamName' => 'abcd-1234']]]], 'cdn' => ['ingestionInfo' => ['ingestionAddress' => 'rtmp://a.rtmp.youtube.com/live2', 'streamName' => 'abcd-1234']]]),
            'www.googleapis.com/youtube/v3/liveBroadcasts/bind*' => Http::response(['id' => 'Bcast123456']),
            'www.googleapis.com/youtube/v3/liveBroadcasts*' => Http::response(['id' => 'Bcast123456', 'status' => ['lifeCycleStatus' => 'created']]),
        ]);
    }

    private function streamedService(array $overrides = []): Service
    {
        return Service::create(['held_on' => today()->addDays(2), 'starts_at' => today()->addDays(2)->setTime(9, 0), 'kind' => 'sunday', 'online_checkin' => true, ...$overrides]);
    }

    public function test_the_hod_signs_in_with_google_and_the_channel_is_connected(): void
    {
        $this->fakeYouTube();
        $this->actingAs($this->hod->user);

        $redirect = $this->post(route('unit.youtube.connect', $this->unit))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertSame('client-123', $query['client_id']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('https://www.googleapis.com/auth/youtube', $query['scope']);
        $this->assertSame(route('youtube.callback'), $query['redirect_uri']);

        $this->get(route('youtube.callback', ['state' => $query['state'], 'code' => 'auth-code']))
            ->assertRedirect(route('unit.setup.index', $this->unit))->assertSessionHas('status');

        $connection = YouTubeConnection::sole();
        $this->assertSame('Royal Tribe TV', $connection->channel_title);
        $this->assertSame('stream-1', $connection->stream_id);
        $this->assertStringNotContainsString('1//refresh', DB::table('youtube_connections')->value('refresh_token'));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://oauth2.googleapis.com/token' && $r['code'] === 'auth-code' && $r['grant_type'] === 'authorization_code');

        // The reusable stream's server and key are saved for OBS.
        $this->assertSame('abcd-1234', StreamDestination::where('platform', 'youtube')->sole()->stream_key);
    }

    public function test_a_wrong_or_missing_state_is_refused(): void
    {
        $this->fakeYouTube();
        $this->actingAs($this->hod->user)->post(route('unit.youtube.connect', $this->unit));

        $this->get(route('youtube.callback', ['state' => 'forged', 'code' => 'x']))->assertForbidden();
        $this->get(route('youtube.callback', ['state' => 'anything', 'code' => 'x']))->assertForbidden(); // used up
        $this->assertSame(0, YouTubeConnection::count());
    }

    public function test_declining_on_googles_page_is_explained(): void
    {
        $this->actingAs($this->hod->user)->post(route('unit.youtube.connect', $this->unit));
        $state = session('youtube_oauth.state');

        $this->get(route('youtube.callback', ['state' => $state, 'error' => 'access_denied']))->assertSessionHasErrors('youtube');
    }

    public function test_connecting_needs_the_google_client_set_up(): void
    {
        config(['services.google.client_id' => null]);

        $this->actingAs($this->hod->user)->post(route('unit.youtube.connect', $this->unit))->assertSessionHasErrors('youtube');
        $this->get(route('unit.setup.index', $this->unit))->assertSee('An administrator needs to add the Google OAuth client ID');
    }

    public function test_only_media_leaders_connect_or_manage_broadcasts(): void
    {
        $member = Member::factory()->active()->create(['unit_id' => $this->unit->id]);
        $service = $this->streamedService();

        $this->actingAs($member->user)->post(route('unit.youtube.connect', $this->unit))->assertForbidden();
        $this->post(route('unit.youtube.create', [$this->unit, $service]))->assertForbidden();
    }

    public function test_broadcasts_are_created_ahead_for_streamed_services(): void
    {
        $this->fakeYouTube();
        $this->connection();
        $streamed = $this->streamedService();
        $notStreamed = $this->streamedService(['kind' => 'special', 'online_checkin' => false]);
        $farOff = $this->streamedService(['held_on' => today()->addDays(20), 'starts_at' => today()->addDays(20)->setTime(9, 0), 'kind' => 'midweek']);

        $this->artisan('youtube:sync')->expectsOutput('Created 1 YouTube broadcasts, checked 0, 0 problems.');
        $this->artisan('youtube:sync')->expectsOutput('Created 0 YouTube broadcasts, checked 0, 0 problems.');

        $setup = ServiceSetup::where('service_id', $streamed->id)->sole();
        $this->assertTrue($setup->youtube_managed);
        $this->assertSame('Bcast123456', $setup->youtube_video_id);
        $this->assertNull(ServiceSetup::where('service_id', $notStreamed->id)->first());
        $this->assertNull(ServiceSetup::where('service_id', $farOff->id)->first());
        $this->assertGreaterThan(0, $setup->tasks()->count()); // the checklist came with it

        Http::assertSent(function (HttpRequest $r) use ($streamed) {
            return str_starts_with($r->url(), 'https://www.googleapis.com/youtube/v3/liveBroadcasts?part=snippet') && $r->method() === 'POST'
                && $r['status']['privacyStatus'] === 'public' && $r['contentDetails']['enableAutoStart'] === true
                && $r['snippet']['scheduledStartTime'] === $streamed->starts_at->copy()->utc()->toIso8601ZuluString()
                && $r->hasHeader('Authorization', 'Bearer ya29.access');
        });
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'liveBroadcasts/bind') && str_contains($r->url(), 'streamId=stream-1') && str_contains($r->url(), 'id=Bcast123456'));
    }

    public function test_the_website_goes_live_and_ends_with_youtube(): void
    {
        Http::fake(['www.googleapis.com/youtube/v3/liveBroadcasts*' => Http::sequence()
            ->push(['items' => [['id' => 'Bcast123456', 'status' => ['lifeCycleStatus' => 'ready']]]])
            ->push(['items' => [['id' => 'Bcast123456', 'status' => ['lifeCycleStatus' => 'live']]]])
            ->push(['items' => [['id' => 'Bcast123456', 'status' => ['lifeCycleStatus' => 'complete']]]])]);
        config(['church.youtube.days_ahead' => 0]);
        $this->connection();
        $service = $this->streamedService(['held_on' => today(), 'starts_at' => now()->subMinutes(5)]);
        ServiceSetup::forService($service)->forceFill(['youtube_video_id' => 'Bcast123456', 'youtube_managed' => true, 'youtube_status' => 'created'])->save();

        $this->artisan('youtube:sync');
        $this->assertFalse(ServiceSetup::sole()->isLive());

        $this->artisan('youtube:sync');
        $this->assertTrue(ServiceSetup::sole()->isLive());
        Setting::applyToConfig();
        $this->get(route('watch'))->assertSee('embed/Bcast123456', false)->assertSee('LIVE ON YOUTUBE');

        $this->artisan('youtube:sync');
        $this->assertFalse(ServiceSetup::sole()->isLive());
        $this->assertSame('complete', ServiceSetup::sole()->youtube_status);
        Setting::applyToConfig();
        $this->get(route('watch'))->assertSee('embed/Bcast123456', false)->assertDontSee('LIVE ON YOUTUBE');

        // Finished broadcasts aren't checked again.
        $this->artisan('youtube:sync')->expectsOutput('Created 0 YouTube broadcasts, checked 0, 0 problems.');
    }

    public function test_expired_tokens_refresh_and_a_revoked_sign_in_asks_to_reconnect(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::sequence()
                ->push(['access_token' => 'ya29.fresh', 'expires_in' => 3599])
                ->push(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400),
            'www.googleapis.com/youtube/v3/*' => Http::response(['items' => []]),
        ]);
        $connection = $this->connection(['expires_at' => now()->subMinute()]);
        $service = $this->streamedService(['held_on' => today(), 'starts_at' => now()]);
        ServiceSetup::forService($service)->forceFill(['youtube_video_id' => 'Bcast123456', 'youtube_managed' => true, 'youtube_status' => 'ready'])->save();
        config(['church.youtube.days_ahead' => 0]);

        $this->artisan('youtube:sync');
        $this->assertSame('ya29.fresh', $connection->fresh()->access_token);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'liveBroadcasts') && $r->hasHeader('Authorization', 'Bearer ya29.fresh'));

        $connection->fresh()->update(['expires_at' => now()->subMinute()]);
        $this->artisan('youtube:sync')->expectsOutput('Created 0 YouTube broadcasts, checked 0, 1 problems.');
        $this->assertNotNull($connection->fresh()->problem);
        $this->actingAs($this->hod->user)->get(route('unit.setup.index', $this->unit))->assertSee('Needs reconnecting')->assertSee('Connect again');

        // While the sign-in is broken, nothing more is tried.
        Http::fake();
        $this->artisan('youtube:sync');
        Http::assertNothingSent();
    }

    public function test_a_failed_broadcast_is_not_retried_automatically_but_can_be_by_hand(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/liveStreams*' => Http::response(['items' => [['id' => 'stream-1', 'cdn' => []]]]),
            'www.googleapis.com/youtube/v3/liveBroadcasts*' => Http::sequence()
                ->push(['error' => ['code' => 403, 'message' => 'not enabled', 'errors' => [['reason' => 'liveStreamingNotEnabled']]]], 403)
                ->push(['id' => 'Bcast654321', 'status' => ['lifeCycleStatus' => 'created']])
                ->push(['id' => 'Bcast654321']),
        ]);
        $this->connection();
        $service = $this->streamedService();

        $this->artisan('youtube:sync')->expectsOutput('Created 0 YouTube broadcasts, checked 0, 1 problems.');
        $setup = ServiceSetup::sole();
        $this->assertStringContainsString('Live streaming isn\'t turned on', $setup->youtube_error);

        $this->artisan('youtube:sync')->expectsOutput('Created 0 YouTube broadcasts, checked 0, 0 problems.');

        $this->actingAs($this->hod->user)->get(route('unit.setup.show', [$this->unit, $service]))->assertSee('Try again');
        $this->post(route('unit.youtube.create', [$this->unit, $service]))->assertSessionHas('status', 'Broadcast created on YouTube.');
        $this->assertSame('Bcast654321', $setup->fresh()->youtube_video_id);
        $this->assertNull($setup->fresh()->youtube_error);
    }

    public function test_titles_are_pushed_and_broadcasts_removed_but_not_while_live(): void
    {
        Http::fake(['www.googleapis.com/youtube/v3/*' => Http::response(['id' => 'Bcast123456'])]);
        $this->connection();
        $service = $this->streamedService();
        $setup = ServiceSetup::forService($service);
        $setup->forceFill(['youtube_video_id' => 'Bcast123456', 'youtube_managed' => true, 'youtube_status' => 'ready'])->save();
        $this->actingAs($this->hod->user);

        $this->put(route('unit.setup.update', [$this->unit, $service]), ['stream_title' => 'Thanksgiving Sunday', 'youtube_video_id' => 'hijack1234'])->assertRedirect();
        $this->assertSame('Bcast123456', $setup->fresh()->youtube_video_id);
        $this->put(route('unit.youtube.push', [$this->unit, $service]))->assertSessionHas('status');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && $r['id'] === 'Bcast123456' && $r['snippet']['title'] === 'Thanksgiving Sunday');

        $setup->forceFill(['youtube_status' => 'live'])->save();
        $this->delete(route('unit.youtube.destroy', [$this->unit, $service]))->assertStatus(422);
        $setup->forceFill(['youtube_status' => 'ready'])->save();
        $this->delete(route('unit.youtube.destroy', [$this->unit, $service]))->assertSessionHas('status');
        $this->assertNull($setup->fresh()->youtube_video_id);
    }

    public function test_disconnecting_revokes_the_sign_in(): void
    {
        Http::fake();
        $this->connection();

        $this->actingAs($this->hod->user)->delete(route('unit.youtube.disconnect', $this->unit))->assertSessionHas('status');

        $this->assertSame(0, YouTubeConnection::count());
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://oauth2.googleapis.com/revoke' && $r['token'] === '1//refresh');
    }

    public function test_nothing_runs_until_a_channel_is_connected(): void
    {
        Http::fake();
        $this->streamedService();

        $this->artisan('youtube:sync')->expectsOutput('Created 0 YouTube broadcasts, checked 0, 0 problems.');
        Http::assertNothingSent();
    }

    public function test_settings_keep_the_client_secret_encrypted(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), [
            'services__google__client_id' => 'abc.apps.googleusercontent.com', 'services__google__client_secret' => 'GOCSPX-secret', 'church__youtube__privacy' => 'unlisted',
        ])->assertSessionHasNoErrors();
        $this->assertNotSame('GOCSPX-secret', Setting::firstWhere('key', 'services.google.client_secret')->value);

        $this->put(route('admin.settings.update'), ['church__youtube__privacy' => 'private'])->assertSessionHasErrors('church__youtube__privacy');
    }
}

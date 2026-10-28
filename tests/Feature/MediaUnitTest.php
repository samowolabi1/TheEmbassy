<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Member;
use App\Models\Service;
use App\Models\ServiceSetup;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Models\StreamDestination;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUnitTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Member $hod;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->unit = Unit::factory()->media()->create();
        $this->hod = Member::factory()->active()->create(['name' => 'Bro. Tunde', 'unit_id' => $this->unit->id]);
        $this->unit->update(['hod_member_id' => $this->hod->id]);
        $this->member = Member::factory()->active()->create(['name' => 'Kunle Camera', 'unit_id' => $this->unit->id]);
        config(['services.meta' => ['graph_version' => 'v26.0', 'page_id' => '1234', 'page_token' => 'EAAtoken', 'instagram_id' => '9876']]);
    }

    // ---- Access ----

    public function test_team_members_get_the_media_tools_but_not_the_unit_admin(): void
    {
        $this->actingAs($this->member->user);
        $this->get(route('account'))->assertSee('Media team');
        $this->get(route('unit.equipment.index', $this->unit))->assertOk()->assertDontSee('Add an item');
        $this->get(route('unit.show', $this->unit))->assertForbidden();
        $this->post(route('unit.equipment.store', $this->unit), ['name' => 'X', 'category' => 'camera'])->assertForbidden();

        $outsider = Member::factory()->active()->create();
        $this->actingAs($outsider->user)->get(route('unit.equipment.index', $this->unit))->assertForbidden();
    }

    public function test_staff_with_the_media_role_run_the_media_tools(): void
    {
        $this->actingAs(User::factory()->staff('media')->create())->get(route('unit.social.index', $this->unit))->assertOk()->assertSee('Publish (or schedule)');
    }

    public function test_media_tools_only_exist_for_the_media_unit(): void
    {
        $choir = Unit::factory()->create(['hod_member_id' => $this->hod->id]);

        $this->actingAs($this->hod->user)->get(route('unit.equipment.index', $choir))->assertForbidden();
    }

    // ---- Equipment ----

    public function test_equipment_is_checked_out_returned_and_faults_fixed(): void
    {
        $this->actingAs($this->hod->user);
        $this->post(route('unit.equipment.store', $this->unit), ['name' => 'Sony FX30', 'category' => 'camera', 'tag' => 'CAM-01', 'value' => '1500000'])->assertRedirect();
        $camera = Equipment::sole();
        $this->assertSame(150_000_000, $camera->value);

        $this->post(route('unit.equipment.out', [$this->unit, $camera]), ['member_id' => $this->member->id, 'due_on' => today()->addDay()->toDateString()])->assertRedirect();
        $this->assertSame('checked_out', $camera->fresh()->status);
        $this->post(route('unit.equipment.out', [$this->unit, $camera]), ['member_id' => $this->member->id])->assertStatus(422);
        $this->get(route('unit.equipment.index', $this->unit))->assertSee('with Kunle Camera');

        $this->post(route('unit.equipment.in', [$this->unit, $camera]), ['condition' => 'faulty'])->assertSessionHasErrors('fault');
        $this->post(route('unit.equipment.in', [$this->unit, $camera]), ['condition' => 'faulty', 'fault' => 'Lens cap missing, autofocus sticks'])->assertRedirect();
        $camera->refresh();
        $this->assertSame('repair', $camera->status);
        $issue = $camera->issues()->sole();

        $this->post(route('unit.equipment.resolve', [$this->unit, $issue]), ['resolution' => 'Serviced at Computer Village', 'cost' => '15000'])->assertRedirect();
        $this->assertSame('available', $camera->fresh()->status);
        $this->assertSame(1_500_000, $issue->fresh()->cost);
    }

    public function test_team_members_report_faults(): void
    {
        $mic = Equipment::create(['name' => 'Shure SM58', 'category' => 'audio']);

        $this->actingAs($this->member->user)->post(route('unit.equipment.report', [$this->unit, $mic]), ['description' => 'Crackles when moved'])->assertRedirect();

        $this->assertSame('faulty', $mic->fresh()->condition);
        $this->actingAs($this->hod->user)->get(route('unit.equipment.index', $this->unit))->assertSee('Crackles when moved');
    }

    // ---- Service setup and streaming ----

    public function test_service_setup_starts_from_the_checklist_and_the_team_ticks_it_off(): void
    {
        $this->unit->update(['checklist' => "Cameras ready\nSound check\nStream test"]);
        $service = Service::create(['held_on' => today()->addDay(), 'starts_at' => today()->addDay()->setTime(9, 0), 'kind' => 'sunday']);

        $this->actingAs($this->member->user)->get(route('unit.setup.show', [$this->unit, $service]))->assertOk()->assertSee('Sound check');
        $setup = ServiceSetup::sole();
        $this->assertSame(3, $setup->tasks()->count());

        $task = $setup->tasks()->first();
        $this->post(route('unit.setup.tasks.toggle', [$this->unit, $task]))->assertRedirect();
        $this->assertNotNull($task->fresh()->done_at);
        $this->get(route('unit.setup.index', $this->unit))->assertSee('1/3 ready');

        // Team members can't go live.
        $this->post(route('unit.setup.live', [$this->unit, $service]))->assertForbidden();
    }

    public function test_going_live_switches_the_watch_page_player(): void
    {
        $service = Service::create(['held_on' => today(), 'starts_at' => now(), 'kind' => 'sunday']);
        $this->actingAs($this->hod->user)->get(route('unit.setup.show', [$this->unit, $service]));

        $this->post(route('unit.setup.live', [$this->unit, $service]))->assertSessionHasErrors('youtube_video_id');
        $this->put(route('unit.setup.update', [$this->unit, $service]), ['youtube_video_id' => 'dQw4w9WgXcQ', 'stream_title' => 'Sunday service'])->assertRedirect();
        $this->post(route('unit.setup.live', [$this->unit, $service]))->assertSessionHas('status');

        Setting::applyToConfig();
        $this->get(route('watch'))->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('LIVE ON YOUTUBE');

        $this->post(route('unit.setup.end', [$this->unit, $service]));
        Setting::applyToConfig();
        $this->get(route('watch'))->assertSee('dQw4w9WgXcQ')->assertDontSee('LIVE ON YOUTUBE');
    }

    public function test_stream_keys_are_encrypted_and_only_shown_to_leaders(): void
    {
        $this->actingAs($this->hod->user)->post(route('unit.streams.store', $this->unit), [
            'platform' => 'youtube', 'label' => 'YouTube main', 'server_url' => 'rtmp://a.rtmp.youtube.com/live2', 'stream_key' => 'abcd-efgh-ijkl-mnop',
        ])->assertRedirect();
        $destination = StreamDestination::sole();

        $this->assertStringNotContainsString('abcd-efgh', DB::table('stream_destinations')->value('stream_key'));
        $this->get(route('unit.setup.index', $this->unit))->assertSee('mnop')->assertDontSee('abcd-efgh');
        $this->get(route('unit.streams.key', [$this->unit, $destination]))->assertSee('abcd-efgh-ijkl-mnop');

        // Updating without a key keeps the saved one.
        $this->put(route('unit.streams.update', [$this->unit, $destination]), ['platform' => 'youtube', 'label' => 'YouTube', 'server_url' => 'rtmp://a.rtmp.youtube.com/live2', 'is_active' => '1']);
        $this->assertSame('abcd-efgh-ijkl-mnop', $destination->fresh()->stream_key);

        $this->actingAs($this->member->user)->get(route('unit.streams.key', [$this->unit, $destination]))->assertForbidden();
        $this->actingAs($this->hod->user)->post(route('unit.streams.store', $this->unit), ['platform' => 'other', 'label' => 'X', 'server_url' => 'http://evil', 'stream_key' => 'k'])->assertSessionHasErrors('server_url');
    }

    // ---- Social media ----

    public function test_team_drafts_go_to_the_hod_who_publishes_to_facebook_and_instagram(): void
    {
        Http::fake([
            'graph.facebook.com/v26.0/1234/photos' => Http::response(['id' => '555', 'post_id' => '1234_555']),
            'graph.facebook.com/v26.0/9876/media' => Http::response(['id' => 'container-1']),
            'graph.facebook.com/v26.0/9876/media_publish' => Http::response(['id' => 'ig-777']),
        ]);

        $this->actingAs($this->member->user)->post(route('unit.social.store', $this->unit), [
            'caption' => 'Join us this Sunday! #RoyalTribe', 'media' => UploadedFile::fake()->image('flyer.jpg', 1080, 1080),
            'platforms' => ['facebook', 'instagram', 'whatsapp'], 'intent' => 'publish', // a member can only submit
        ])->assertRedirect();
        $post = SocialPost::sole();
        $this->assertSame('awaiting_approval', $post->status);
        Http::assertNothingSent();

        $this->actingAs($this->hod->user)->post(route('unit.social.act', [$this->unit, $post]), ['intent' => 'publish'])->assertSessionHas('status', 'Published.');
        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertSame('1234_555', $post->results['facebook']['id']);
        $this->assertSame('ig-777', $post->results['instagram']['id']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/1234/photos') && $r['caption'] === 'Join us this Sunday! #RoyalTribe' && str_contains($r['url'], '/storage/social/') && $r->hasHeader('Authorization', 'Bearer EAAtoken'));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/9876/media_publish') && $r['creation_id'] === 'container-1');

        $this->get(route('unit.social.show', [$this->unit, $post]))->assertSee('View post')->assertSee('Share on WhatsApp');
    }

    public function test_instagram_videos_publish_once_processing_finishes(): void
    {
        Http::fake([
            'graph.facebook.com/v26.0/9876/media' => Http::response(['id' => 'container-2']),
            'graph.facebook.com/v26.0/container-2*' => Http::sequence()->push(['status_code' => 'IN_PROGRESS'])->push(['status_code' => 'FINISHED']),
            'graph.facebook.com/v26.0/9876/media_publish' => Http::response(['id' => 'reel-1']),
        ]);
        $this->actingAs($this->hod->user)->post(route('unit.social.store', $this->unit), [
            'caption' => 'Sunday highlights', 'media' => UploadedFile::fake()->create('clip.mp4', 2000, 'video/mp4'), 'platforms' => ['instagram'], 'intent' => 'publish',
        ]);
        $post = SocialPost::sole();
        $this->assertSame('video', $post->media_type);
        $this->assertSame('publishing', $post->status);

        $this->artisan('social:publish')->expectsOutput('Worked on 1 social posts.');
        $this->assertSame('published', $post->fresh()->status);
        $this->assertSame('reel-1', $post->fresh()->results['instagram']['id']);
        Http::assertSentCount(4); // create, check, check, publish
    }

    public function test_scheduled_posts_go_out_when_due(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '1'])]);
        $this->actingAs($this->hod->user)->post(route('unit.social.store', $this->unit), [
            'caption' => 'Midweek service tonight', 'platforms' => ['facebook'], 'publish_at' => now()->addHour()->format('Y-m-d\TH:i'), 'intent' => 'publish',
        ]);
        $post = SocialPost::sole();
        $this->assertSame('scheduled', $post->status);

        $this->artisan('social:publish');
        $this->assertSame('scheduled', $post->fresh()->status);

        $this->travel(61)->minutes();
        $this->artisan('social:publish');
        $this->assertSame('published', $post->fresh()->status);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/1234/feed') && $r['message'] === 'Midweek service tonight');
    }

    public function test_failures_are_shown_and_can_be_retried(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['error' => ['message' => '(#200) Permissions error']], 403)
            ->push(['id' => '42'])]);
        $this->actingAs($this->hod->user)->post(route('unit.social.store', $this->unit), ['caption' => 'Hello', 'platforms' => ['facebook'], 'intent' => 'publish']);
        $post = SocialPost::sole();

        $this->assertSame('failed', $post->status);
        $this->get(route('unit.social.show', [$this->unit, $post]))->assertSee('(#200) Permissions error');

        $this->post(route('unit.social.act', [$this->unit, $post]), ['intent' => 'retry'])->assertSessionHas('status', 'Published.');
    }

    public function test_instagram_needs_media_and_not_connected_accounts_fail_clearly(): void
    {
        $this->actingAs($this->hod->user)->post(route('unit.social.store', $this->unit), ['caption' => 'Text only', 'platforms' => ['instagram'], 'intent' => 'publish'])->assertSessionHasErrors('media');

        config(['services.meta.page_token' => null]);
        Http::fake();
        $this->post(route('unit.social.store', $this->unit), ['caption' => 'Hello', 'platforms' => ['facebook'], 'intent' => 'publish']);
        $post = SocialPost::sole();
        $this->assertSame('failed', $post->status);
        $this->assertStringContainsString('Not connected', $post->results['facebook']['error']);
        Http::assertNothingSent();
    }

    public function test_settings_store_the_page_token_encrypted(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), ['services__meta__page_token' => 'EAAsecret', 'services__meta__page_id' => '1234']);

        $this->assertNotSame('EAAsecret', Setting::firstWhere('key', 'services.meta.page_token')->value);
        config(['services.meta.page_token' => null]);
        Setting::applyToConfig();
        $this->assertSame('EAAsecret', config('services.meta.page_token'));
        $this->get(route('admin.settings.edit'))->assertDontSee('EAAsecret')->assertSee('Remove the saved token');

        // Saving again without typing it keeps it.
        $this->put(route('admin.settings.update'), ['services__meta__page_id' => '1234']);
        Setting::applyToConfig();
        $this->assertSame('EAAsecret', config('services.meta.page_token'));
    }

    public function test_the_hod_edits_the_checklist_new_setups_start_from(): void
    {
        $this->actingAs($this->hod->user)->put(route('unit.setup.checklist', $this->unit), ['checklist' => "Batteries charged\nSlides loaded"])->assertRedirect();
        $service = Service::create(['held_on' => today()->addDay(), 'kind' => 'sunday']);

        $this->get(route('unit.setup.show', [$this->unit, $service]))->assertSee('Batteries charged');
        $this->assertSame(['Batteries charged', 'Slides loaded'], ServiceSetup::sole()->tasks()->pluck('title')->all());
        $this->actingAs($this->member->user)->put(route('unit.setup.checklist', $this->unit), ['checklist' => 'x'])->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\StudioGuest;
use App\Models\StudioPreset;
use App\Models\StudioScreen;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    private Unit $media;

    private Member $hod;

    private Member $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->media = Unit::factory()->media()->create();
        $this->hod = Member::factory()->active()->create(['unit_id' => $this->media->id]);
        $this->media->update(['hod_member_id' => $this->hod->id]);
        $this->operator = Member::factory()->active()->create(['unit_id' => $this->media->id]);
    }

    public function test_the_team_puts_things_on_screen_and_the_overlay_follows(): void
    {
        $this->actingAs($this->operator->user)->get(route('unit.studio.index', $this->media))->assertOk()->assertSee('Live studio');
        $screen = StudioScreen::sole();
        $state = fn () => $this->getJson(route('studio.overlay.state', $screen->token))->assertOk()->json();

        $this->postJson(route('unit.studio.caption', $this->media), ['name' => 'Pastor Tunde', 'title' => 'Ministering', 'save' => '1'])
            ->assertOk()->assertJsonPath('state.lowerThird.name', 'Pastor Tunde');
        $this->assertSame(['name' => 'Pastor Tunde', 'title' => 'Ministering'], $state()['lowerThird']);
        $this->assertSame(1, StudioPreset::where('type', 'caption')->count());

        $this->postJson(route('unit.studio.scripture', $this->media), ['reference' => 'John 3:16', 'text' => 'For God so loved the world'])->assertOk();
        $this->postJson(route('unit.studio.ticker', $this->media), ['ticker' => 'Midweek service Wednesday 6pm'])->assertOk();
        $this->postJson(route('unit.studio.logo', $this->media))->assertOk()->assertJsonPath('state.logo', true);
        $this->postJson(route('unit.studio.scene', $this->media), ['scene' => 'starting', 'countdown_to' => now()->addMinutes(10)->format('Y-m-d\TH:i')])
            ->assertOk()->assertJsonPath('state.sceneTitle', 'Starting soon');
        $this->assertNotNull($state()['countdownTo']);
        $this->assertSame('John 3:16', $state()['scripture']['reference']);

        $before = $state()['version'];
        $this->postJson(route('unit.studio.caption', $this->media), ['action' => 'hide'])->assertOk()->assertJsonPath('state.lowerThird', null);
        $this->assertGreaterThan($before, $state()['version']);
        $this->postJson(route('unit.studio.caption', $this->media), [])->assertUnprocessable();

        $this->postJson(route('unit.studio.clear', $this->media))->assertOk();
        $cleared = $state();
        $this->assertSame([null, null, null, true], [$cleared['scene'], $cleared['scripture'], $cleared['ticker'], $cleared['logo']]);

        // The overlay page itself, for OBS.
        $this->get(route('studio.overlay', $screen->token))->assertOk()->assertSee('data-layer="lower"', false);
    }

    public function test_the_overlay_needs_its_secret_and_only_leaders_replace_it(): void
    {
        $screen = StudioScreen::current();
        $this->get(route('studio.overlay', 'wrong-token'))->assertNotFound();
        $this->getJson(route('studio.overlay.state', 'wrong-token'))->assertNotFound();

        $this->actingAs($this->operator->user)->post(route('unit.studio.regenerate', $this->media))->assertForbidden();
        $this->actingAs($this->hod->user)->post(route('unit.studio.regenerate', $this->media));
        $this->get(route('studio.overlay', $screen->token))->assertNotFound();
        $this->get(route('studio.overlay', $screen->fresh()->token))->assertOk();

        // People outside the media team can't use the controls.
        $stranger = Member::factory()->active()->create();
        $this->actingAs($stranger->user)->get(route('unit.studio.index', $this->media))->assertForbidden();
        $this->postJson(route('unit.studio.ticker', $this->media), ['ticker' => 'Hacked'])->assertForbidden();
    }

    public function test_guests_get_their_own_links(): void
    {
        $this->actingAs($this->operator->user)->post(route('unit.studio.guests.store', $this->media), ['name' => 'Pastor Grace'])->assertSessionHasNoErrors();
        $guest = StudioGuest::sole();
        $this->assertStringStartsWith('https://vdo.ninja/?push='.$guest->stream_id, $guest->guestUrl());
        $this->assertStringContainsString('view='.$guest->stream_id, $guest->obsUrl());
        $this->get(route('unit.studio.index', $this->media))->assertSee('Pastor Grace')->assertSee($guest->obsUrl());
        $this->delete(route('unit.studio.guests.destroy', [$this->media, $guest]));
        $this->assertModelMissing($guest);
    }
}

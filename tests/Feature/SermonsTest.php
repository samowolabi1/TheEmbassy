<?php

namespace Tests\Feature;

use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SermonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_examples_show_until_the_church_adds_messages(): void
    {
        $this->get(route('watch'))->assertSee('A royal priesthood')->assertSee('Crowned');
    }

    public function test_media_team_adds_a_series_and_messages_that_replace_the_examples(): void
    {
        $this->actingAs(User::factory()->staff('media')->create());

        $this->post(route('admin.series.store'), ['title' => 'Kingdom', 'accent' => 'Builders', 'style' => 'gold', 'is_current' => '1'])->assertRedirect();
        $series = SermonSeries::sole();

        foreach (['Laying foundations' => 14, 'Building on the rock' => 7] as $title => $daysAgo) {
            $this->post(route('admin.sermons.store'), [
                'title' => $title, 'kind' => 'sunday', 'sermon_series_id' => $series->id, 'preached_on' => now()->subDays($daysAgo)->toDateString(),
                'scripture' => 'Matthew 7:24', 'length' => '40 min', 'url' => 'https://youtube.com/watch?v=abc', 'swatch' => 'navy', 'is_published' => '1',
            ])->assertRedirect(route('admin.sermons.index'));
        }
        $this->post(route('admin.sermons.store'), ['title' => 'Draft talk', 'kind' => 'podcast', 'preached_on' => today()->toDateString(), 'swatch' => 'ice']);

        $this->get(route('watch'))
            ->assertSee('Building on the rock')->assertSee('Kingdom Builders · Week 2')->assertSee('Sunday service: Kingdom Builders, week 2')
            ->assertDontSee('A royal priesthood')->assertDontSee('Draft talk');
        $this->get('/')->assertSee('Week 2: Building on the rock')->assertSee('Matthew 7:24 · 40 min');
    }

    public function test_only_one_series_is_now_showing(): void
    {
        $this->actingAs(User::factory()->staff('media')->create());
        $old = SermonSeries::create(['title' => 'Old', 'style' => 'navy', 'is_current' => true]);

        $this->post(route('admin.series.store'), ['title' => 'New', 'style' => 'ice', 'is_current' => '1']);

        $this->assertFalse($old->fresh()->is_current);
    }

    public function test_media_team_controls_the_live_player(): void
    {
        $this->actingAs(User::factory()->staff('media')->create());

        $this->post(route('admin.sermons.live'), ['video_id' => 'dQw4w9WgXcQ', 'is_live' => '1'])->assertRedirect();
        Setting::applyToConfig();

        $this->get(route('watch'))->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('LIVE ON YOUTUBE');
        $this->post(route('admin.sermons.live'), ['video_id' => 'javascript:alert(1)'])->assertSessionHasErrors('video_id');
    }

    public function test_links_must_be_web_addresses(): void
    {
        $this->actingAs(User::factory()->staff('media')->create())->post(route('admin.sermons.store'), [
            'title' => 'X', 'kind' => 'sunday', 'preached_on' => today()->toDateString(), 'swatch' => 'navy', 'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');
        $this->assertSame(0, Sermon::count());
    }

    public function test_finance_cannot_edit_sermons(): void
    {
        $this->actingAs(User::factory()->staff('finance')->create())->get(route('admin.sermons.index'))->assertForbidden();
    }
}

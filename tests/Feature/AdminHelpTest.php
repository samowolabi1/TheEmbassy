<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_page_has_help_with_its_own_text_and_tour(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $pages = [
            'admin.dashboard' => 'dashboard', 'admin.members.index' => 'members', 'admin.first-timers.index' => 'firsttimers',
            'admin.care.index' => 'care', 'admin.attendance.index' => 'attendance', 'admin.units.index' => 'units',
            'admin.subscribers.index' => 'subscribers', 'admin.finance.index' => 'finance', 'admin.money.index' => 'money',
            'admin.income.index' => 'income', 'admin.expenses.index' => 'expenses', 'admin.market.index' => 'market',
            'admin.content' => 'content', 'admin.posts.index' => 'blog', 'admin.gallery.index' => 'gallery',
            'admin.tokens.index' => 'tokens', 'admin.community.index' => 'community', 'admin.notices.index' => 'notices',
            'admin.sermons.index' => 'live', 'admin.events.index' => 'events', 'admin.appointments.index' => 'appointments',
            'admin.library.desk' => 'library', 'admin.feedback.index' => 'feedback', 'admin.testimonies.index' => 'testimonies',
            'admin.prayer-requests.index' => 'prayer', 'admin.messages.index' => 'messages', 'admin.settings.edit' => 'settings',
        ];
        foreach ($pages as $route => $key) {
            $about = config("help.pages.{$key}.about");
            $this->assertNotEmpty($about, $key);
            $response = $this->get(route($route))->assertOk()->assertSee('data-help-panel', false)->assertSee($about);
            if (config('help.tours')[$route] ?? null) {
                $response->assertSee('id="page-tour"', false)->assertSee('Take the tour');
            }
            foreach (config("help.pages.{$key}.faqs") as [$question]) {
                $response->assertSee($question);
            }
        }
        $this->get(route('admin.pages.edit', 'about'))->assertOk()->assertSee(config('help.pages.pages.about'));
    }

    public function test_the_help_config_points_at_real_pages(): void
    {
        foreach (array_keys(config('help.tours')) as $route) {
            $this->assertTrue(Route::has($route), "Tour for missing route {$route}");
            foreach (config('help.tours')[$route] as $step) {
                $this->assertCount(3, $step, $route);
            }
        }
        foreach (config('help.guides') as $slug => $guide) {
            $this->assertTrue(Route::has($guide['route']), "Guide {$slug}");
            $this->assertNotEmpty($guide['steps']);
        }
        $areas = array_keys(config('roles.areas'));
        foreach ([...config('help.pages'), ...config('help.guides')] as $item) {
            if (isset($item['area'])) {
                $this->assertContains($item['area'], $areas);
            }
        }
    }

    public function test_the_help_centre_shows_only_what_each_person_uses(): void
    {
        $media = User::factory()->create(['role' => 'media']);
        $this->actingAs($media)->get(route('admin.help'))->assertOk()
            ->assertSee('Record Sunday&#039;s offering', false)->assertSee('Send an announcement to members&#039; phones', false)
            ->assertDontSee('Approve someone who joined online')->assertDontSee('Why can only the pastor in charge see this?')
            ->assertSee(route('admin.money.index', ['tour' => 1]), false);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Help centre');

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.help'))->assertOk()
            ->assertSee('Approve someone who joined online')->assertSee('Lend and take back a library book');

        $this->post(route('admin.logout'));
        $this->get(route('admin.help'))->assertRedirect();
    }

    public function test_tooltips_explain_tricky_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.attendance.index'))->assertOk()
            ->assertSee('class="tip__button"', false)->assertSee('role="tooltip"', false)
            ->assertSee('Adds the same service on the same day each week');
    }
}

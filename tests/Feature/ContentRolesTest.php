<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_blog_writer_writes_and_publishes_articles_and_nothing_else(): void
    {
        $writer = User::factory()->staff('writer')->create();
        $this->actingAs($writer)->get(route('admin.posts.index'))->assertOk();
        $this->get(route('admin.posts.create'))->assertOk();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Blog')->assertDontSee('Content &amp; announcements', false);

        $this->post(route('admin.posts.store'), [
            'title' => 'Walking in purpose', 'topic' => 'Faith', 'author_name' => 'Sis. Ada', 'swatch' => Post::SWATCHES[0],
            'dek' => 'Finding God\'s plan for your life.', 'body' => "## Start here\n\nGod has a plan.", 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Post::firstWhere('title', 'Walking in purpose')->isLive());

        foreach (['admin.content', 'admin.gallery.index', 'admin.verses.index', 'admin.members.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('admin.pages.edit', 'about'))->assertForbidden();
    }

    public function test_a_website_manager_looks_after_the_website_only(): void
    {
        $web = User::factory()->staff('web')->create();
        $this->actingAs($web);
        foreach (['admin.content', 'admin.posts.index', 'admin.gallery.index', 'admin.verses.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.pages.edit', 'home'))->assertOk();
        foreach (['admin.members.index', 'admin.finance.index', 'admin.settings.edit'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_staff_who_wrote_for_the_blog_before_still_can(): void
    {
        foreach (['pastor', 'secretary', 'media', 'admin'] as $role) {
            $this->actingAs(User::factory()->staff($role)->create())->get(route('admin.posts.index'))->assertOk();
        }
        $this->actingAs(User::factory()->staff('finance')->create())->get(route('admin.posts.index'))->assertForbidden();
    }

    public function test_settings_lists_the_new_roles(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.settings.edit'))->assertSee('Blog writer')->assertSee('Website manager');
    }
}

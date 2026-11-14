<?php

namespace Tests\Feature;

use App\Models\Post;
use Database\Seeders\BlogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BlogDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_adds_articles_once_with_a_draft_and_a_scheduled_one(): void
    {
        Storage::fake('public');
        $this->seed(BlogDemoSeeder::class);
        $this->seed(BlogDemoSeeder::class); // a second run adds nothing

        $demo = Post::where('slug', 'like', 'demo-%');
        $this->assertSame(12, (clone $demo)->count());
        $this->assertSame(10, (clone $demo)->published()->count()); // not the draft or the scheduled one
        $withCover = (clone $demo)->whereNotNull('cover_path')->first();
        Storage::disk('public')->assertExists($withCover->cover_path);

        $post = Post::firstWhere('slug', 'demo-budgeting-on-a-small-salary-without-losing-your-joy');
        $this->get($post->url())->assertOk()->assertSee('Contentment is a skill')->assertSee('<blockquote>', false);
        $this->get(route('blog.index', ['topic' => 'Mental wellness']))->assertOk()->assertSee('Rest is not laziness');
        $this->get(route('blog.index', ['q' => 'Nehemiah']))->assertOk()->assertDontSee('Lessons from Nehemiah');
    }

    public function test_it_refuses_to_run_on_a_live_site(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->app->make(BlogDemoSeeder::class)->run();
    }
}

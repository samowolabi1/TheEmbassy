<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Declaration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'home' => ['/', 'Rooted in Christ.'],
            'about' => ['/about', 'A royal tribe, raising a generation for Christ.'],
            'watch' => ['/watch', 'Binge a whole series.'],
            'blog' => ['/blog', 'Real talk for'],
            'events' => ['/events', 'Regular gatherings'],
            'give' => ['/give', 'Honour God'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_renders(string $path, string $expected): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee($expected)
            ->assertSee(config('church.name'));
    }

    public function test_every_blog_article_has_a_page(): void
    {
        foreach (config('content.posts.posts') as $post) {
            $this->get(route('blog.show', $post['slug']))
                ->assertOk()
                ->assertSee($post['title']);
        }
    }

    public function test_featured_article_shows_its_body(): void
    {
        $this->get('/blog/living-like-royalty-when-life-feels-ordinary')
            ->assertSee('Royalty shows in how you work')
            ->assertSee('Colossians 3:23 (KJV)');
    }

    public function test_unknown_article_is_not_found(): void
    {
        $this->get('/blog/no-such-article')->assertNotFound();
    }

    public function test_current_page_is_marked_in_the_navigation(): void
    {
        $html = $this->get('/watch')->getContent();

        $this->assertMatchesRegularExpression('#href="[^"]*/watch"\s+aria-current="page"\s*>Watch</a>#', $html);
        $this->assertDoesNotMatchRegularExpression('#aria-current="page"\s*>Home</a>#', $html);
    }

    public function test_home_shows_live_announcements_only(): void
    {
        Announcement::factory()->create(['title' => 'Live one']);
        Announcement::factory()->draft()->create(['title' => 'Draft one']);
        Announcement::factory()->scheduled()->create(['title' => 'Future one']);

        $this->get('/')->assertSee('Live one')->assertDontSee('Draft one')->assertDontSee('Future one');
    }

    public function test_home_shows_only_the_three_newest_announcements(): void
    {
        foreach (range(1, 4) as $n) {
            Announcement::factory()->create(['title' => "Notice {$n}", 'published_at' => now()->subHours(10 - $n)]);
        }

        $this->get('/')->assertSeeInOrder(['Notice 4', 'Notice 3', 'Notice 2'])->assertDontSee('Notice 1');
    }

    public function test_home_shows_this_weeks_declaration(): void
    {
        Declaration::factory()->create(['week_of' => now()->startOfWeek(), 'lines' => ['Line A', 'Line B', 'Line C', 'Line D']]);
        Declaration::factory()->create(['week_of' => now()->startOfWeek()->addWeek(), 'lines' => ['Next A', 'Next B', 'Next C', 'Next D']]);

        $this->get('/')->assertSee('Line A')->assertDontSee('Next A');
    }

    public function test_home_works_with_no_announcements_or_declaration(): void
    {
        $this->get('/')->assertOk()->assertSee('No announcements right now.')->assertDontSee('Say it out loud this week.');
    }
}

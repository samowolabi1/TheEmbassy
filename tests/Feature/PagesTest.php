<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
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
            'events' => ['/events', 'Royal Youth Convention'],
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
}

<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): array
    {
        return [
            'title' => 'Faith at work',
            'topic' => 'Faith',
            'dek' => 'Monday matters too.',
            'body' => "Hello **world**.\n\n> Whatsoever ye do, do it heartily.\n> — Colossians 3:23",
            'swatch' => 'navy',
            'status' => 'published',
            ...$overrides,
        ];
    }

    public function test_starter_articles_are_imported_with_their_bodies(): void
    {
        $post = Post::firstWhere('slug', 'living-like-royalty-when-life-feels-ordinary');

        $this->assertTrue($post->is_featured);
        $this->assertStringContainsString('<figure class="scripture">', $post->body_html);
        $this->assertStringContainsString('<div class="reflect">', $post->body_html);
        $this->get('/blog')->assertSee('Living like royalty when life feels ordinary');
    }

    public function test_media_staff_write_and_publish_an_article(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->staff('media')->create());

        $this->post(route('admin.posts.store'), $this->article([
            'cover' => UploadedFile::fake()->image('cover.jpg', 2400, 1600),
            'cover_alt' => 'Young people praying',
        ]))->assertRedirect();

        $post = Post::firstWhere('slug', 'faith-at-work');
        $this->assertTrue($post->isLive());
        $this->assertSame(1, $post->reading_minutes);
        Storage::disk('public')->assertExists([$post->cover_path, $post->cover_thumb_path]);
        $this->assertSame(2000, getimagesizefromstring(Storage::disk('public')->get($post->cover_path))[0]);

        $this->get('/blog/faith-at-work')
            ->assertOk()
            ->assertSee('<strong>world</strong>', false)
            ->assertSee('<figcaption>Colossians 3:23</figcaption>', false)
            ->assertSee('Young people praying')
            ->assertSee('"@type":"BlogPosting"', false);
    }

    public function test_body_html_is_cleaned(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.posts.store'), $this->article([
            'body' => "<script>alert(1)</script>\n\n[click](javascript:alert(1))\n\n<img src=x onerror=alert(1)>",
        ]));

        $html = Post::firstWhere('slug', 'faith-at-work')->body_html;
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_drafts_and_scheduled_articles_stay_hidden_but_can_be_previewed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->post(route('admin.posts.store'), $this->article(['status' => 'draft']));
        $this->post(route('admin.posts.store'), $this->article(['title' => 'Later', 'status' => 'scheduled', 'publish_at' => now()->addDay()->format('Y-m-d\TH:i')]));
        $draft = Post::firstWhere('slug', 'faith-at-work');
        $later = Post::firstWhere('slug', 'later');

        $this->assertSame('draft', $draft->status());
        $this->assertSame('scheduled', $later->status());
        $this->get(route('admin.posts.preview', $draft))->assertOk()->assertSee('Preview.')->assertSee('noindex', false);

        auth()->logout();
        $this->get('/blog/faith-at-work')->assertNotFound();
        $this->get('/blog/later')->assertNotFound();
        $this->get('/blog')->assertDontSee('Faith at work');
        $this->get(route('blog.feed'))->assertDontSee('Faith at work');

        $this->travel(2)->days();
        $this->get('/blog/later')->assertOk();
        $this->get(route('blog.feed'))->assertOk()->assertSee('Later');
    }

    public function test_scheduling_needs_a_date_and_slugs_are_checked(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.posts.store'), $this->article(['status' => 'scheduled']))->assertSessionHasErrors('publish_at');
        $this->post(route('admin.posts.store'), $this->article(['slug' => 'Bad Slug!']))->assertSessionHasErrors('slug');
        $this->post(route('admin.posts.store'), $this->article(['slug' => 'dating-with-purpose']))->assertSessionHasErrors('slug');

        // Same title twice gets a numbered address.
        $this->post(route('admin.posts.store'), $this->article());
        $this->post(route('admin.posts.store'), $this->article());
        $this->assertTrue(Post::where('slug', 'faith-at-work-2')->exists());
    }

    public function test_renamed_articles_redirect_from_their_old_address(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $post = Post::firstWhere('slug', 'dating-with-purpose');

        $this->put(route('admin.posts.update', $post), $this->article(['title' => $post->title, 'topic' => $post->topic, 'slug' => 'dating-on-purpose']))->assertRedirect();

        $this->get('/blog/dating-with-purpose')->assertRedirect('/blog/dating-on-purpose')->assertStatus(301);
        $this->get('/blog/dating-on-purpose')->assertOk();
    }

    public function test_only_one_article_is_featured(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.posts.store'), $this->article(['is_featured' => '1']));

        $this->assertSame(1, Post::where('is_featured', true)->count());
        $this->get('/blog')->assertSeeInOrder(['FEATURED', 'Faith at work']);
    }

    public function test_topic_filter_search_and_pages(): void
    {
        Post::factory()->count(12)->create(['topic' => 'Purpose']);

        $this->get('/blog?topic=Relationships')->assertSee('Dating with purpose')->assertDontSee('Your first salary');
        $this->get('/blog?q=salary')->assertSee('Your first salary')->assertDontSee('Dating with purpose')->assertSee('1 result');
        $this->get('/blog?q=%25')->assertOk();
        $this->get('/blog?page=2')->assertOk()->assertSee('Page 2 of');
    }

    public function test_inline_pictures_upload_and_return_markdown(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->staff('media')->create());

        $this->postJson(route('admin.posts.upload'), ['image' => UploadedFile::fake()->image('choir.png', 800, 600)])
            ->assertOk()
            ->assertJsonPath('markdown', fn ($md) => str_starts_with($md, '![choir](') && str_contains($md, '/storage/blog/inline/'));
        $this->postJson(route('admin.posts.upload'), ['image' => UploadedFile::fake()->create('notes.pdf', 10)])->assertUnprocessable();
    }

    public function test_staff_without_the_content_area_cannot_write(): void
    {
        $this->actingAs(User::factory()->staff('finance')->create());

        $this->get(route('admin.posts.index'))->assertForbidden();
        $this->post(route('admin.posts.store'), $this->article())->assertForbidden();
    }

    public function test_deleting_an_article_removes_its_cover(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.posts.store'), $this->article(['cover' => UploadedFile::fake()->image('c.jpg', 900, 600)]));
        $post = Post::firstWhere('slug', 'faith-at-work');

        $this->delete(route('admin.posts.destroy', $post))->assertRedirect(route('admin.posts.index'));

        Storage::disk('public')->assertMissing($post->cover_path);
        $this->assertModelMissing($post);
    }

    public function test_home_page_and_sitemap_list_articles(): void
    {
        $this->get('/')->assertSee('Dating with purpose');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/blog/dating-with-purpose')->assertSee('/gallery');
    }
}

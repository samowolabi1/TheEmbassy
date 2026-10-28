<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function albumWithPhotos(int $count = 2): GalleryAlbum
    {
        $this->actingAs(User::factory()->staff('media')->create());
        $this->post(route('admin.gallery.store'), ['title' => 'Youth Convention 2026', 'taken_on' => '2026-08-15']);
        $album = GalleryAlbum::firstWhere('slug', 'youth-convention-2026');
        $this->post(route('admin.gallery.upload', $album), [
            'photos' => collect(range(1, $count))->map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg", 3000, 2000))->all(),
        ]);

        return $album->fresh();
    }

    public function test_media_staff_create_an_album_and_upload_photos(): void
    {
        $album = $this->albumWithPhotos(3);

        $this->assertFalse($album->is_published);
        $this->assertCount(3, $album->items);
        $item = $album->items->first();
        Storage::disk('public')->assertExists([$item->path, $item->thumb_path]);
        $this->assertSame([2000, 1333], [$item->width, $item->height]);
        $this->assertSame(640, getimagesizefromstring(Storage::disk('public')->get($item->thumb_path))[0]);

        // Hidden until published.
        $this->get(route('gallery.show', $album))->assertNotFound();
        $this->get('/gallery')->assertDontSee('Youth Convention 2026');

        $this->put(route('admin.gallery.update', $album), ['title' => 'Youth Convention 2026', 'taken_on' => '2026-08-15', 'is_published' => '1'])->assertSessionHasNoErrors();
        $this->get('/gallery')->assertSee('Youth Convention 2026')->assertSee('3 items');
        $this->get(route('gallery.show', $album))->assertOk()->assertSee($item->url())->assertSee('data-lightbox', false);
    }

    public function test_an_empty_album_cannot_be_published(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.gallery.store'), ['title' => 'Empty']);
        $album = GalleryAlbum::firstWhere('slug', 'empty');

        $this->put(route('admin.gallery.update', $album), ['title' => 'Empty', 'is_published' => '1'])->assertSessionHasErrors('is_published');
        $this->assertFalse($album->fresh()->is_published);
    }

    public function test_photo_location_data_is_removed(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $album = GalleryAlbum::factory()->create();
        $jpeg = UploadedFile::fake()->image('gps.jpg', 1200, 800);

        $this->post(route('admin.gallery.upload', $album), ['photos' => [$jpeg]]);

        $saved = Storage::disk('public')->get($album->items()->first()->path);
        $this->assertSame('RIFF', substr($saved, 0, 4)); // WebP, re-encoded without EXIF
        $this->assertStringNotContainsString('Exif', $saved);
    }

    public function test_uploads_are_validated_and_unreadable_files_are_skipped(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $album = GalleryAlbum::factory()->create();

        $this->post(route('admin.gallery.upload', $album), ['photos' => [UploadedFile::fake()->create('doc.pdf', 10)]])->assertSessionHasErrors('photos.0');
        $this->post(route('admin.gallery.upload', $album), ['photos' => [UploadedFile::fake()->createWithContent('broken.jpg', 'not really a jpeg')]])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Could not read: broken.jpg'));
        $this->assertSame(0, $album->items()->count());

        $this->postJson(route('admin.gallery.upload', $album), ['photos' => [UploadedFile::fake()->image('ok.jpg', 400, 300)]])
            ->assertOk()->assertJsonPath('redirect', route('admin.gallery.edit', $album));
        $this->assertSame(1, $album->items()->count());
    }

    public function test_youtube_videos_captions_order_and_cover(): void
    {
        $album = $this->albumWithPhotos(2);
        [$first, $second] = $album->items;

        $this->post(route('admin.gallery.video', $album), ['link' => 'not a link'])->assertSessionHasErrors('link');
        $this->post(route('admin.gallery.video', $album), ['link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10', 'caption' => 'Highlights']);
        $video = $album->items()->where('kind', 'video')->first();
        $this->assertSame('dQw4w9WgXcQ', $video->youtube_id);

        $this->put(route('admin.gallery.items', $album), [
            'items' => [
                $first->id => ['caption' => 'Worship', 'position' => 9],
                $second->id => ['caption' => 'Choir', 'position' => 1],
            ],
            'cover_item_id' => $second->id,
        ])->assertSessionHasNoErrors();

        $album->refresh();
        $this->assertSame($second->id, $album->cover_item_id);
        $this->assertSame(['Choir', 'Highlights', 'Worship'], $album->items->pluck('caption')->all());

        // Items from another album can't be changed through this one.
        $other = GalleryAlbum::factory()->create();
        $stranger = $other->items()->create(['kind' => 'video', 'youtube_id' => 'abcdefghijk', 'caption' => 'Theirs']);
        $this->put(route('admin.gallery.items', $album), ['items' => [$stranger->id => ['caption' => 'Mine']]]);
        $this->assertSame('Theirs', $stranger->fresh()->caption);
        $this->delete(route('admin.gallery.items.destroy', [$album, $stranger]))->assertNotFound();
    }

    public function test_deleting_photos_and_albums_removes_files(): void
    {
        $album = $this->albumWithPhotos(2);
        [$first, $second] = $album->items;
        $album->update(['is_published' => true, 'cover_item_id' => $first->id]);

        $this->delete(route('admin.gallery.items.destroy', [$album, $first]));
        Storage::disk('public')->assertMissing([$first->path, $first->thumb_path]);
        $this->assertNull($album->fresh()->cover_item_id);

        // Removing the last photo hides the album.
        $this->delete(route('admin.gallery.items.destroy', [$album, $second]));
        $this->assertFalse($album->fresh()->is_published);

        $album = $this->albumWithPhotos(1);
        $path = $album->items->first()->path;
        $this->delete(route('admin.gallery.destroy', $album))->assertRedirect(route('admin.gallery.index'));
        Storage::disk('public')->assertMissing($path);
        $this->assertModelMissing($album);
    }

    public function test_youtube_links_are_recognised(): void
    {
        foreach (['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://youtube.com/live/dQw4w9WgXcQ?si=x', 'dQw4w9WgXcQ'] as $link) {
            $this->assertSame('dQw4w9WgXcQ', GalleryItem::youtubeId($link), $link);
        }
        $this->assertNull(GalleryItem::youtubeId('https://evil.example/watch?v=dQw4w9WgXcQ'));
    }

    public function test_year_filter_and_access(): void
    {
        GalleryAlbum::factory()->create(['title' => 'Easter 2025', 'taken_on' => '2025-04-20'])->items()->create(['kind' => 'video', 'youtube_id' => 'dQw4w9WgXcQ']);
        GalleryAlbum::factory()->create(['title' => 'Easter 2026', 'taken_on' => '2026-04-05'])->items()->create(['kind' => 'video', 'youtube_id' => 'dQw4w9WgXcQ']);

        $this->get('/gallery?year=2025')->assertSee('Easter 2025')->assertDontSee('Easter 2026');
        $this->get('/gallery?year=abc')->assertSessionHasErrors('year');

        $this->actingAs(User::factory()->staff('usher')->create());
        $this->get(route('admin.gallery.index'))->assertForbidden();
    }
}

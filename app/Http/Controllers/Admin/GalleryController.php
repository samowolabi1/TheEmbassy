<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Services\Content\Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Photo and video albums for the Gallery page.
 */
class GalleryController extends Controller
{
    /** Photos accepted in one upload. */
    public const BATCH = 40;

    public function index(): View
    {
        return view('admin.gallery.index', [
            'albums' => GalleryAlbum::withCount('items')->with('cover', 'items')->orderByDesc('taken_on')->orderByDesc('id')->paginate(24),
            'events' => $this->events(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $album = GalleryAlbum::create([...$data, 'slug' => GalleryAlbum::uniqueSlug($data['title']), 'is_published' => false]);

        return redirect()->route('admin.gallery.edit', $album)->with('status', 'Album created. Add photos, then publish it.');
    }

    public function edit(GalleryAlbum $album): View
    {
        return view('admin.gallery.edit', [
            'album' => $album->load('items'),
            'events' => $this->events(),
            'batch' => self::BATCH,
        ]);
    }

    public function update(Request $request, GalleryAlbum $album): RedirectResponse
    {
        $data = $this->validated($request);
        $publish = $request->boolean('is_published');
        if ($publish && ! $album->items()->exists()) {
            return back()->withErrors(['is_published' => 'Add at least one photo or video before publishing.'])->withInput();
        }
        $album->update([...$data, 'is_published' => $publish]);

        return back()->with('status', $publish ? 'Album saved and showing on the Gallery page.' : 'Album saved (hidden from the website).');
    }

    public function destroy(GalleryAlbum $album, Images $images): RedirectResponse
    {
        foreach ($album->items as $item) {
            $images->delete($item->path, $item->thumb_path);
        }
        $album->delete();

        return redirect()->route('admin.gallery.index')->with('status', "Album \"{$album->title}\" deleted.");
    }

    /**
     * Several photos at once. Pictures that can't be read are skipped and reported.
     */
    public function upload(Request $request, GalleryAlbum $album, Images $images): RedirectResponse|JsonResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:'.self::BATCH],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
        ], [
            'photos.max' => 'Upload up to '.self::BATCH.' photos at a time.',
            'photos.*.mimes' => 'Photos must be JPG, PNG or WebP.',
            'photos.*.max' => 'Each photo must be 15 MB or smaller.',
        ]);

        $position = (int) $album->items()->max('position');
        $added = 0;
        $failed = [];
        foreach ($request->file('photos') as $file) {
            try {
                $saved = $images->store($file, 'gallery/'.$album->id);
            } catch (RuntimeException) {
                $failed[] = $file->getClientOriginalName();

                continue;
            }
            $album->items()->create([...$saved, 'kind' => 'photo', 'position' => ++$position]);
            $added++;
        }
        $album->touch();

        $status = $added.' '.str('photo')->plural($added).' added.';
        if ($failed) {
            $status .= ' Could not read: '.implode(', ', array_slice($failed, 0, 5)).(count($failed) > 5 ? '…' : '');
        }

        // The upload page sends photos in the background to show progress, then reloads.
        if ($request->expectsJson()) {
            session()->flash('status', $status);

            return response()->json(['redirect' => route('admin.gallery.edit', $album)]);
        }

        return back()->with('status', $status);
    }

    public function video(Request $request, GalleryAlbum $album): RedirectResponse
    {
        $data = $request->validate([
            'link' => ['required', 'string', 'max:300'],
            'caption' => ['nullable', 'string', 'max:200'],
        ]);
        $id = GalleryItem::youtubeId($data['link']);
        if (! $id) {
            return back()->withErrors(['link' => 'Paste a YouTube link, e.g. https://youtu.be/dQw4w9WgXcQ'])->withInput();
        }

        $album->items()->create([
            'kind' => 'video',
            'youtube_id' => $id,
            'caption' => $data['caption'] ?? null,
            'position' => (int) $album->items()->max('position') + 1,
        ]);

        return back()->with('status', 'Video added.');
    }

    /**
     * Captions, order and cover for every item in the album, saved together.
     */
    public function items(Request $request, GalleryAlbum $album): RedirectResponse
    {
        $ids = $album->items()->pluck('id')->all();
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.caption' => ['nullable', 'string', 'max:200'],
            'items.*.position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cover_item_id' => ['nullable', Rule::in($ids)],
        ]);

        DB::transaction(function () use ($album, $data, $ids) {
            foreach ($data['items'] ?? [] as $id => $fields) {
                if (in_array((int) $id, $ids, true)) {
                    GalleryItem::whereKey($id)->update([
                        'caption' => $fields['caption'] ?? null,
                        'position' => (int) ($fields['position'] ?? 0),
                    ]);
                }
            }
            $album->update(['cover_item_id' => $data['cover_item_id'] ?? null]);
        });

        return back()->with('status', 'Captions and order saved.');
    }

    public function destroyItem(GalleryAlbum $album, GalleryItem $item, Images $images): RedirectResponse
    {
        abort_unless($item->gallery_album_id === $album->id, 404);
        $images->delete($item->path, $item->thumb_path);
        $item->delete();
        if ($album->cover_item_id === $item->id) {
            $album->update(['cover_item_id' => null]);
        }
        if ($album->is_published && ! $album->items()->exists()) {
            $album->update(['is_published' => false]);
        }

        return back()->with('status', $item->isVideo() ? 'Video removed.' : 'Photo deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'taken_on' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_id' => ['nullable', Rule::exists('events', 'id')],
        ]);
    }

    private function events()
    {
        return Event::orderByDesc('starts_at')->take(40)->get(['id', 'title', 'starts_at']);
    }
}

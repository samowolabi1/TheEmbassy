<?php

namespace App\Http\Controllers;

use App\Models\GalleryAlbum;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GalleryController extends Controller
{
    /**
     * Published albums, newest first, by year.
     */
    public function index(Request $request): View
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = $request->integer('year') ?: null;

        $albums = GalleryAlbum::published()
            ->with(['cover', 'items' => fn ($q) => $q->take(1)])
            ->withCount('items')
            ->when($year, fn ($q) => $q->whereYear('taken_on', $year))
            ->paginate(12)
            ->withQueryString();

        $years = GalleryAlbum::published()->whereNotNull('taken_on')->pluck('taken_on')
            ->map(fn ($d) => $d->year)->unique()->sortDesc()->values();

        return view('gallery.index', ['albums' => $albums, 'years' => $years, 'year' => $year]);
    }

    public function show(GalleryAlbum $album): View
    {
        abort_unless($album->is_published, 404);
        $album->load('items', 'event');

        return view('gallery.show', [
            'album' => $album,
            'more' => GalleryAlbum::published()->whereKeyNot($album->id)->with(['cover', 'items' => fn ($q) => $q->take(1)])->withCount('items')->take(3)->get(),
        ]);
    }
}

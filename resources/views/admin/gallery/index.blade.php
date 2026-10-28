@extends('layouts.admin', ['title' => 'Gallery', 'active' => 'gallery'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Gallery</h1>
            <span class="page-head__sub">Albums of photos and videos for the Gallery page.</span>
        </div>
        <div class="actions">
            <a href="{{ route('gallery.index') }}" class="btn btn--outline" target="_blank">View gallery</a>
        </div>
    </header>

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Albums</h2></div>
            @forelse ($albums as $album)
                @php $cover = $album->coverItem(); @endphp
                <div class="list-item">
                    @if ($cover)
                        <img src="{{ $cover->thumbUrl() }}" alt="" class="list-thumb" width="64" height="48" loading="lazy">
                    @else
                        <span class="list-thumb bg-ice" aria-hidden="true"></span>
                    @endif
                    <span class="list-item__main">
                        <a href="{{ route('admin.gallery.edit', $album) }}" class="list-item__title">{{ $album->title }}</a>
                        <span class="list-item__meta">{{ $album->taken_on?->format('j M Y') ?? 'No date' }} · {{ $album->items_count }} {{ \Illuminate\Support\Str::plural('item', $album->items_count) }}</span>
                    </span>
                    @if ($album->is_published)
                        <span class="pill pill--live">Showing</span>
                    @else
                        <span class="pill pill--draft">Hidden</span>
                    @endif
                    <span class="list-item__actions">
                        <a href="{{ route('admin.gallery.edit', $album) }}" class="btn btn--ghost btn--xs">Open<span class="sr-only"> {{ $album->title }}</span></a>
                    </span>
                </div>
            @empty
                <p class="empty-state">No albums yet. Create the first one.</p>
            @endforelse
            @include('admin.partials.pager', ['paginator' => $albums])
        </section>

        <section class="box side-col">
            <h2>New album</h2>
            <form method="POST" action="{{ route('admin.gallery.store') }}" class="form">
                @csrf
                @include('admin.gallery.album-fields', ['album' => new \App\Models\GalleryAlbum(['taken_on' => today()])])
                <div><button type="submit" class="btn btn--gold">Create album</button></div>
            </form>
        </section>
    </div>
@endsection

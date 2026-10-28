@extends('layouts.admin', ['title' => $album->title, 'active' => 'gallery'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.gallery.index') }}" class="box__link">← Gallery</a>
            <h1>{{ $album->title }}</h1>
            <span class="page-head__sub">
                {{ $album->items->count() }} {{ \Illuminate\Support\Str::plural('item', $album->items->count()) }} ·
                {{ $album->is_published ? 'Showing on the Gallery page' : 'Hidden from the website' }}
            </span>
        </div>
        @if ($album->is_published)
            <div class="actions"><a href="{{ route('gallery.show', $album) }}" class="btn btn--outline" target="_blank">View album</a></div>
        @endif
    </header>

    @if ($errors->any())
        <p class="flash flash--error" role="alert">{{ $errors->first() }}</p>
    @endif

    <div class="row">
        <section class="box grow form">
            <h2>Add photos</h2>
            <form method="POST" action="{{ route('admin.gallery.upload', $album) }}" enctype="multipart/form-data" class="form" data-photo-upload>
                @csrf
                <label class="dropzone" data-dropzone>
                    <x-icon name="image" :size="28" />
                    <span class="dropzone__title">Choose photos or drop them here</span>
                    <span class="field__hint">JPG, PNG or WebP, up to {{ $batch }} at a time, 15 MB each. Photos are resized and location data is removed.</span>
                    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required class="sr-only" data-photo-input>
                    <span class="dropzone__count" data-photo-count aria-live="polite"></span>
                </label>
                <progress max="100" value="0" hidden data-photo-progress></progress>
                <div><button type="submit" class="btn btn--gold">Upload</button></div>
            </form>

            <h2 style="margin-top: 8px">Add a YouTube video</h2>
            <form method="POST" action="{{ route('admin.gallery.video', $album) }}" class="form-row">
                @csrf
                <label class="field">Link
                    <input type="url" name="link" value="{{ old('link') }}" required maxlength="300" placeholder="https://youtu.be/…">
                    @error('link') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Caption <input type="text" name="caption" maxlength="200"></label>
                <div class="field" style="justify-content: flex-end"><button type="submit" class="btn btn--navy">Add video</button></div>
            </form>
        </section>

        <section class="box side-col">
            <h2>Album details</h2>
            <form method="POST" action="{{ route('admin.gallery.update', $album) }}" class="form">
                @csrf
                @method('PUT')
                @include('admin.gallery.album-fields')
                <label class="login__remember"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $album->is_published))>Show on the Gallery page</label>
                @error('is_published') <span class="field-error">{{ $message }}</span> @enderror
                <div class="actions">
                    <button type="submit" class="btn btn--navy">Save</button>
                    <button type="submit" form="delete-album" class="btn btn--danger btn--xs">Delete album</button>
                </div>
            </form>
            <form id="delete-album" method="POST" action="{{ route('admin.gallery.destroy', $album) }}" data-confirm="Delete this album and all its photos for good?">
                @csrf
                @method('DELETE')
            </form>
        </section>
    </div>

    @if ($album->items->isNotEmpty())
        <section class="box form">
            <div class="box__head" style="padding: 0"><h2>Photos and videos</h2><span class="box__note">Lower numbers show first. Pick one as the cover.</span></div>
            <form method="POST" action="{{ route('admin.gallery.items', $album) }}" id="items-form">
                @csrf
                @method('PUT')
            </form>
            <ul class="item-grid" role="list">
                @foreach ($album->items as $item)
                    <li class="item-tile">
                        <span class="item-tile__image">
                            <img src="{{ $item->thumbUrl() }}" alt="" loading="lazy">
                            @if ($item->isVideo()) <span class="pill pill--info item-tile__kind">Video</span> @endif
                        </span>
                        <label class="login__remember"><input type="radio" name="cover_item_id" value="{{ $item->id }}" form="items-form" @checked($album->cover_item_id === $item->id)>Cover</label>
                        <label class="field">Caption <input type="text" name="items[{{ $item->id }}][caption]" value="{{ $item->caption }}" maxlength="200" form="items-form"></label>
                        <span class="item-tile__row">
                            <label class="field">Order <input type="number" name="items[{{ $item->id }}][position]" value="{{ $item->position }}" min="0" max="100000" form="items-form"></label>
                            <button type="submit" form="delete-item-{{ $item->id }}" class="btn btn--danger btn--xs">Delete<span class="sr-only"> item {{ $loop->iteration }}</span></button>
                        </span>
                        <form id="delete-item-{{ $item->id }}" method="POST" action="{{ route('admin.gallery.items.destroy', [$album, $item]) }}" data-confirm="Delete this {{ $item->isVideo() ? 'video' : 'photo' }}?">
                            @csrf
                            @method('DELETE')
                        </form>
                    </li>
                @endforeach
            </ul>
            <div class="save-bar"><button type="submit" form="items-form" class="btn btn--navy">Save captions &amp; order</button></div>
        </section>
    @endif
@endsection

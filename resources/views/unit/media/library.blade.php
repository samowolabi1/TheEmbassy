@extends('layouts.unit', ['title' => 'File library', 'active' => 'library'])

@php
    $folderId = ctype_digit((string) $filters['folder']) ? (int) $filters['folder'] : null;
    $current = $folderId ? $folders->firstWhere('id', $folderId) : null;
    $icons = ['image' => 'IMG', 'video' => '▶', 'audio' => '♪', 'design' => 'DES', 'document' => 'DOC', 'archive' => 'ZIP', 'link' => 'LINK'];
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>{{ $filters['from'] ? 'Delivered for requests' : ($current?->name ?? ($filters['folder'] === 'none' ? 'Not in a folder' : 'File library')) }}</h1>
            <span class="page-head__sub">{{ $current?->description ?? 'Photos, videos, designs, logos and documents for the team. Private: only the media team can open them.' }} {{ \App\Models\MediaFile::bytes($used) }} stored.</span>
        </div>
        <div class="actions"><a href="#add-files" class="btn btn--gold"><x-icon name="plus" :size="16" />Add files</a></div>
    </header>

    <nav class="tabs" aria-label="Folders">
        <a href="{{ route('unit.library.index', $unit) }}" class="tab" @if (! $filters['folder'] && ! $filters['from']) aria-current="page" @endif>Everything</a>
        @foreach ($folders as $f)
            <a href="{{ route('unit.library.index', [$unit, 'folder' => $f->id]) }}" class="tab" @if ($folderId === $f->id) aria-current="page" @endif>{{ $f->name }} ({{ $f->files_count }})</a>
        @endforeach
        <a href="{{ route('unit.library.index', [$unit, 'folder' => 'none']) }}" class="tab" @if ($filters['folder'] === 'none') aria-current="page" @endif>Not in a folder</a>
        <a href="{{ route('unit.library.index', [$unit, 'from' => 'requests']) }}" class="tab" @if ($filters['from']) aria-current="page" @endif>From requests</a>
    </nav>

    <section class="box box--flush">
        <form method="GET" action="{{ route('unit.library.index', $unit) }}" class="box__head filters-bar" role="search">
            @if ($filters['folder'])<input type="hidden" name="folder" value="{{ $filters['folder'] }}">@endif
            @if ($filters['from'])<input type="hidden" name="from" value="requests">@endif
            <label class="field filters-bar__search">
                <span class="sr-only">Search files</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search by name, tag or description">
            </label>
            <label class="sr-only" for="filter-kind">Kind</label>
            <select id="filter-kind" name="kind" class="select-sm">
                <option value="">All kinds</option>
                @foreach (\App\Models\MediaFile::KINDS as $key => $label)
                    <option value="{{ $key }}" @selected($filters['kind'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn--navy btn--xs">Search</button>
        </form>

        @if ($files->isEmpty())
            <p class="empty-state">{{ $filters['q'] || $filters['kind'] ? 'Nothing matches.' : 'No files here yet.' }}</p>
        @else
            <ul class="item-grid" role="list" style="padding: 16px">
                @foreach ($files as $file)
                    <li class="item-tile">
                        <a href="{{ $file->isLink() ? $file->url : route('media-files.show', [$file, $file->showsInline() ? 'view' : 'download']) }}" class="item-tile__image" @if ($file->isLink() || $file->showsInline()) target="_blank" rel="noopener noreferrer" @endif style="display: grid; place-items: center; background: var(--wash, #f3efe6); color: var(--navy); font-weight: 800; font-size: 22px; text-decoration: none">
                            @if ($file->thumb_path)
                                <img src="{{ route('media-files.show', [$file, 'thumb']) }}" alt="" loading="lazy">
                            @else
                                <span aria-hidden="true">{{ $icons[$file->kind] ?? 'FILE' }}</span>
                            @endif
                            <span class="pill pill--info item-tile__kind">{{ $file->kind === 'link' ? 'Link' : strtoupper($file->extension()) }}</span>
                            <span class="sr-only">Open {{ $file->title }}</span>
                        </a>
                        <span class="stack" style="gap: 2px; min-width: 0">
                            <strong style="overflow-wrap: anywhere">{{ $file->title }}</strong>
                            <span class="list-item__meta">{{ $file->size ? $file->sizeLabel().' · ' : '' }}{{ $file->created_at->format('j M Y') }}{{ $file->uploader ? ' · '.($file->uploader->member?->name ?? $file->uploader->name) : '' }}</span>
                            @if ($file->request)
                                <a href="{{ route('unit.requests.show', [$unit, $file->request]) }}" class="list-item__meta">For: {{ $file->request->title }}</a>
                            @endif
                            @if ($file->tagList())
                                <span class="list-item__meta">
                                    @foreach ($file->tagList() as $tag)
                                        <a href="{{ route('unit.library.index', [$unit, 'q' => $tag]) }}">#{{ $tag }}</a>
                                    @endforeach
                                </span>
                            @endif
                        </span>
                        <span style="display: flex; gap: 6px; flex-wrap: wrap">
                            @unless ($file->isLink())
                                <a href="{{ route('media-files.show', [$file, 'download']) }}" class="btn btn--ghost btn--xs">Download</a>
                            @endunless
                        </span>
                        <details>
                            <summary class="box__link" style="cursor: pointer">Edit</summary>
                            <form method="POST" action="{{ route('unit.library.update', [$unit, $file]) }}" class="form" style="margin-top: 8px">
                                @csrf
                                @method('PUT')
                                <label class="field">Title <input type="text" name="title" value="{{ $file->title }}" required maxlength="150"></label>
                                <label class="field">Folder
                                    <select name="media_folder_id">
                                        <option value="">Not in a folder</option>
                                        @foreach ($folders as $f)
                                            <option value="{{ $f->id }}" @selected($file->media_folder_id === $f->id)>{{ $f->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field">Tags <input type="text" name="tags" value="{{ $file->tags }}" maxlength="300" placeholder="sunday, choir, 2026"></label>
                                <label class="field">Description <input type="text" name="description" value="{{ $file->description }}" maxlength="500"></label>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap">
                                    <button type="submit" class="btn btn--navy btn--xs">Save</button>
                                    @if ($canLead || $file->uploaded_by === auth()->id())
                                        <button type="submit" form="delete-file-{{ $file->id }}" class="btn btn--danger btn--xs">Delete</button>
                                    @endif
                                </div>
                            </form>
                            <form id="delete-file-{{ $file->id }}" method="POST" action="{{ route('unit.library.destroy', [$unit, $file]) }}" data-confirm="Delete {{ $file->title }} for good?">
                                @csrf
                                @method('DELETE')
                            </form>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif
        @include('admin.partials.pager', ['paginator' => $files])
    </section>

    <div class="row">
        <section class="box grow" id="add-files">
            <h2>Add files</h2>
            <form method="POST" action="{{ route('unit.library.upload', $unit) }}" enctype="multipart/form-data" class="form" data-photo-upload data-noun="file" data-processing="Saving…">
                @csrf
                <label class="dropzone" data-dropzone>
                    <x-icon name="image" :size="28" />
                    <span class="dropzone__title">Choose files or drop them here</span>
                    <span class="field__hint">Photos, videos, audio, designs (PSD, AI, SVG…), documents and zips. Up to 20 at a time, {{ $maxMb }} MB each. For bigger files, add a link below.</span>
                    <input type="file" name="files[]" multiple required class="sr-only" data-photo-input>
                    <span class="dropzone__count" data-photo-count aria-live="polite"></span>
                </label>
                <progress max="100" value="0" hidden data-photo-progress></progress>
                @error('files') <span class="field-error">{{ $message }}</span> @enderror
                @error('files.*') <span class="field-error">{{ $message }}</span> @enderror
                <div class="form-row">
                    <label class="field">Folder
                        <select name="media_folder_id">
                            <option value="">Not in a folder</option>
                            @foreach ($folders as $f)
                                <option value="{{ $f->id }}" @selected($folderId === $f->id)>{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Tags (optional)
                        <input type="text" name="tags" maxlength="300" placeholder="sunday, choir, 2026">
                    </label>
                </div>
                <div><button type="submit" class="btn btn--gold">Upload</button></div>
            </form>

            <h2 style="margin-top: 8px">Add a link</h2>
            <p class="box__note" style="margin: 0">For big videos or folders kept on Google Drive, Dropbox or YouTube.</p>
            <form method="POST" action="{{ route('unit.library.link', $unit) }}" class="form">
                @csrf
                <div class="form-row">
                    <label class="field">Link
                        <input type="url" name="url" value="{{ old('url') }}" required maxlength="500" placeholder="https://…">
                        @error('url') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Title
                        <input type="text" name="title" value="{{ old('title') }}" required maxlength="150" placeholder="e.g. Easter Sunday raw footage">
                        @error('title') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
                <div class="form-row">
                    <label class="field">Folder
                        <select name="media_folder_id">
                            <option value="">Not in a folder</option>
                            @foreach ($folders as $f)
                                <option value="{{ $f->id }}" @selected($folderId === $f->id)>{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Tags (optional) <input type="text" name="tags" maxlength="300"></label>
                </div>
                <div><button type="submit" class="btn btn--navy">Add link</button></div>
            </form>
        </section>

        @if ($canLead)
            <section class="box" style="flex: 0 1 320px">
                <h2>Folders</h2>
                <form method="POST" action="{{ route('unit.library.folders.store', $unit) }}" class="form">
                    @csrf
                    <label class="field">New folder
                        <input type="text" name="name" required maxlength="80" placeholder="e.g. Sunday services 2026">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Description (optional) <input type="text" name="description" maxlength="200"></label>
                    <div><button type="submit" class="btn btn--navy">Make folder</button></div>
                </form>
                @if ($current)
                    <h2 style="margin-top: 8px">This folder</h2>
                    <form method="POST" action="{{ route('unit.library.folders.update', [$unit, $current]) }}" class="form">
                        @csrf
                        @method('PUT')
                        <label class="field">Name <input type="text" name="name" value="{{ $current->name }}" required maxlength="80"></label>
                        <label class="field">Description <input type="text" name="description" value="{{ $current->description }}" maxlength="200"></label>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap">
                            <button type="submit" class="btn btn--navy btn--xs">Save</button>
                            <button type="submit" form="delete-folder" class="btn btn--danger btn--xs">Remove folder</button>
                        </div>
                    </form>
                    <form id="delete-folder" method="POST" action="{{ route('unit.library.folders.destroy', [$unit, $current]) }}" data-confirm="Remove the folder? Its files stay in the library.">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </section>
        @endif
    </div>
@endsection

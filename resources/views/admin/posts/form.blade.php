@extends('layouts.admin', ['title' => $post->exists ? 'Edit article' : 'New article', 'active' => 'blog'])

@php
    $status = old('status', $post->status());
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.posts.index') }}" class="box__link">← Blog</a>
            <h1>{{ $post->exists ? 'Edit article' : 'New article' }}</h1>
            @if ($post->exists)
                <span class="page-head__sub">
                    @switch($post->status())
                        @case('draft') Draft, not on the website. @break
                        @case('scheduled') Scheduled for {{ $post->published_at->format('j M Y, g:i A') }}. @break
                        @default Live at <a href="{{ $post->url() }}" target="_blank" class="box__link">{{ str_replace(['https://', 'http://'], '', $post->url()) }}</a>
                    @endswitch
                </span>
            @endif
        </div>
        @if ($post->exists)
            <div class="actions">
                <a href="{{ route('admin.posts.preview', $post) }}" class="btn btn--outline" target="_blank">Preview</a>
            </div>
        @endif
    </header>

    @if (session('preview'))
        <p class="flash" role="status">Saved. <a href="{{ session('preview') }}" target="_blank" class="box__link">Open the preview</a></p>
    @endif
    @if ($errors->any())
        <p class="flash flash--error" role="alert">Please check the highlighted fields.</p>
    @endif

    <form method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" enctype="multipart/form-data" class="row" data-unsaved-warning>
        @csrf
        @if ($post->exists) @method('PUT') @endif

        <section class="box grow form">
            <label class="field">Title
                <input type="text" name="title" value="{{ old('title', $post->title) }}" required maxlength="160" data-slug-source>
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">Standfirst
                <textarea name="dek" maxlength="300" rows="2" style="height: 80px" placeholder="One or two sentences under the title">{{ old('dek', $post->dek) }}</textarea>
                @error('dek') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <div class="field">
                <span id="body-label">Article</span>
                <div class="md-toolbar" role="toolbar" aria-label="Formatting" data-md-toolbar>
                    <button type="button" class="btn btn--ghost btn--xs" data-md="h2" title="Heading">H2</button>
                    <button type="button" class="btn btn--ghost btn--xs" data-md="h3" title="Small heading">H3</button>
                    <button type="button" class="btn btn--ghost btn--xs" data-md="bold" title="Bold"><strong>B</strong></button>
                    <button type="button" class="btn btn--ghost btn--xs" data-md="italic" title="Italic"><em>I</em></button>
                    <button type="button" class="btn btn--ghost btn--xs" data-md="link">Link</button>
                    <button type="button" class="btn btn--ghost btn--xs" data-md="list">• List</button>
                    <button type="button" class="btn btn--ghost btn--xs" data-md="numbered">1. List</button>
                    <button type="button" class="btn btn--ghost btn--xs" data-md="scripture">Scripture</button>
                    <button type="button" class="btn btn--ghost btn--xs" data-md="reflect">Reflect box</button>
                    <label class="btn btn--ghost btn--xs md-toolbar__upload">Picture
                        <input type="file" accept="image/jpeg,image/png,image/webp" data-md-image data-upload-url="{{ route('admin.posts.upload') }}" class="sr-only">
                    </label>
                </div>
                <textarea name="body" aria-labelledby="body-label" class="md-editor" rows="22" maxlength="100000" data-md-editor>{{ old('body', $post->body) }}</textarea>
                <span class="field__hint" data-md-status>Write in Markdown: blank line between paragraphs, ## for headings, **bold**, *italic*. A quote ending in a line starting "— " becomes a scripture card. <span data-word-count></span></span>
                @error('body') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <details class="md-help">
                <summary>Formatting help</summary>
                <pre>## A heading

A paragraph. **Bold**, *italic* and [a link](https://example.com).

- A list item
- Another

> And whatsoever ye do, do it heartily, as to the Lord.
> — Colossians 3:23 (KJV)

:::reflect Reflect this week
1. A question to think about
2. Another one
:::</pre>
            </details>
        </section>

        <aside class="side-col stack" style="gap: 16px">
            <section class="box form">
                <fieldset class="choice-group">
                    <legend>Status</legend>
                    <div class="choices">
                        <label class="choice"><input type="radio" name="status" value="draft" @checked($status === 'draft') data-status><span>Draft</span></label>
                        <label class="choice"><input type="radio" name="status" value="published" @checked($status === 'published') data-status><span>Publish</span></label>
                        <label class="choice"><input type="radio" name="status" value="scheduled" @checked($status === 'scheduled') data-status><span>Schedule</span></label>
                    </div>
                </fieldset>
                <label class="field" data-publish-at @if ($status !== 'scheduled') hidden @endif>Go live on
                    <input type="datetime-local" name="publish_at" value="{{ old('publish_at', $post->status() === 'scheduled' ? $post->published_at->format('Y-m-d\TH:i') : '') }}">
                    @error('publish_at') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="login__remember"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured))>Feature at the top of the blog</label>
                <div class="actions">
                    <button type="submit" class="btn btn--navy">Save</button>
                    <button type="submit" name="then" value="preview" class="btn btn--outline">Save &amp; preview</button>
                </div>
            </section>

            <section class="box form">
                <label class="field">Topic
                    <input type="text" name="topic" value="{{ old('topic', $post->topic) }}" list="topics" required maxlength="60">
                    <datalist id="topics">
                        @foreach ($topics as $topic) <option value="{{ $topic }}"> @endforeach
                    </datalist>
                    @error('topic') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div class="field">
                    <span>Cover photo</span>
                    @if ($post->coverUrl())
                        <img src="{{ $post->thumbUrl() }}" alt="" class="cover-preview">
                        <label class="login__remember"><input type="checkbox" name="remove_cover" value="1">Remove this photo</label>
                    @endif
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="file-input">
                    <span class="field__hint">JPG, PNG or WebP up to 15 MB. Without one, the card uses the colour below.</span>
                    @error('cover') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <label class="field">Describe the photo (for screen readers)
                    <input type="text" name="cover_alt" value="{{ old('cover_alt', $post->cover_alt) }}" maxlength="200">
                </label>
                <label class="field">Card colour
                    <select name="swatch">
                        @foreach ($swatches as $swatch)
                            <option value="{{ $swatch }}" @selected(old('swatch', $post->swatch) === $swatch)>{{ ucfirst($swatch) }}</option>
                        @endforeach
                    </select>
                </label>
            </section>

            <section class="box form">
                <label class="field">Author
                    <input type="text" name="author_name" value="{{ old('author_name', $post->author_name) }}" maxlength="100">
                </label>
                <label class="field">About the author
                    <textarea name="author_bio" maxlength="300" style="height: 80px">{{ old('author_bio', $post->author_bio) }}</textarea>
                </label>
            </section>

            <details class="box form" @if ($errors->hasAny(['slug', 'meta_description'])) open @endif>
                <summary class="box__link" style="cursor: pointer">Address &amp; search engines</summary>
                <label class="field">Web address
                    <input type="text" name="slug" value="{{ old('slug', $post->slug) }}" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="made from the title">
                    <span class="field__hint">/blog/<strong>this-part</strong>. Old addresses keep working if you change it.</span>
                    @error('slug') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Search description
                    <textarea name="meta_description" maxlength="300" style="height: 80px" placeholder="Uses the standfirst if empty">{{ old('meta_description', $post->meta_description) }}</textarea>
                </label>
            </details>

            @if ($post->exists)
                <button type="submit" form="delete-post" class="btn btn--danger btn--xs" style="align-self: flex-start">Delete article</button>
            @endif
        </aside>
    </form>
    @if ($post->exists)
        <form id="delete-post" method="POST" action="{{ route('admin.posts.destroy', $post) }}" data-confirm="Delete this article for good?">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection

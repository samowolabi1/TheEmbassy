@extends('layouts.admin', ['title' => $post->exists ? 'Edit article' : 'New article', 'active' => 'blog'])

@php
    $publishes = auth()->user()->canPublishArticles();
    $status = old('status', $publishes ? $post->status() : ($post->isWaitingForApproval() ? 'review' : 'draft'));
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.posts.index') }}" class="box__link">← Blog</a>
            <h1>{{ $post->exists ? 'Edit article' : 'New article' }}</h1>
            @if ($post->exists)
                <span class="page-head__sub">
                    @switch($post->review ? 'review' : $post->status())
                        @case('review') {{ $post->stateLabel() }}, not on the website yet. @break
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

    @if ($post->isWaitingForApproval() && $publishes)
        <section class="box review-box">
            <h2>Waiting for your approval</h2>
            <p class="box__note" style="margin: 0">{{ $post->writer?->name ?? 'A blog writer' }} sent this {{ $post->submitted_at?->diffForHumans() }}.
                {{ $post->wants_live_at?->isFuture() ? 'They\'d like it to go live on '.$post->wants_live_at->format('j M Y, g:i A').'.' : 'It goes live as soon as you approve it.' }}
                Read it and use Preview to see it as readers will. You can correct it below and press Save first.</p>
            <div class="review-box__actions">
                <form method="POST" action="{{ route('admin.posts.approve', $post) }}" data-confirm="{{ $post->wants_live_at?->isFuture() ? 'Approve it to go live on '.$post->wants_live_at->format('j M, g:i A').'?' : 'Approve it and put it on the website now?' }}" data-confirm-ok="Approve">
                    @csrf
                    <button type="submit" class="btn btn--gold">Approve and publish</button>
                </form>
                <details class="list-item--edit" @if ($errors->has('note')) open @endif>
                    <summary class="btn btn--outline">Send back with a note</summary>
                    <form method="POST" action="{{ route('admin.posts.send-back', $post) }}" class="form" style="margin-top: 12px">
                        @csrf
                        <label class="field">What should change?
                            <textarea name="note" rows="3" maxlength="500" required placeholder="e.g. Lovely piece. Please add the scripture reference in the second section and shorten the opening.">{{ old('note') }}</textarea>
                            @error('note') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <div><button type="submit" class="btn btn--navy">Send back</button></div>
                    </form>
                </details>
            </div>
        </section>
    @elseif ($post->isWaitingForApproval())
        <p class="flash" role="status" style="background: var(--wash); color: var(--navy)">Sent for approval {{ $post->submitted_at?->diffForHumans() }}. You can still make changes; saving keeps it in the queue.</p>
    @elseif ($post->needsChanges())
        <section class="box review-box review-box--changes">
            <h2>{{ $publishes ? 'Sent back to the writer' : 'Please make a few changes' }}</h2>
            <p style="margin: 0">"{{ $post->review_note }}"</p>
            <p class="box__note" style="margin: 0">From {{ \App\Models\User::find($post->reviewed_by)?->name ?? 'an editor' }}, {{ $post->reviewed_at?->diffForHumans() }}.{{ $publishes ? '' : ' Make the changes, then choose "Send for approval" and save.' }}</p>
        </section>
    @endif

    @if (session('preview'))
        <p class="flash" role="status">Saved. <a href="{{ session('preview') }}" target="_blank" class="box__link">Open the preview</a></p>
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
                        @if ($publishes)
                            <label class="choice"><input type="radio" name="status" value="published" @checked($status === 'published') data-status><span>Publish</span></label>
                            <label class="choice"><input type="radio" name="status" value="scheduled" @checked($status === 'scheduled') data-status><span>Schedule</span></label>
                        @else
                            <label class="choice"><input type="radio" name="status" value="review" @checked($status === 'review') data-status><span>Send for approval</span></label>
                        @endif
                    </div>
                    @unless ($publishes)
                        <span class="field__hint">An editor reads it before it goes on the website. You'll get an email when it's approved or if it needs changes.</span>
                    @endunless
                </fieldset>
                <label class="field" data-publish-at @if (! in_array($status, ['scheduled', 'review'], true)) hidden @endif>{{ $publishes ? 'Go live on' : 'Go live on (optional)' }}
                    <input type="datetime-local" name="publish_at" value="{{ old('publish_at', $post->status() === 'scheduled' ? $post->published_at->format('Y-m-d\TH:i') : ($post->wants_live_at?->isFuture() ? $post->wants_live_at->format('Y-m-d\TH:i') : '')) }}">
                    @unless ($publishes)<span class="field__hint">Leave empty for as soon as it's approved.</span>@endunless
                    @error('publish_at') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                @if ($publishes)
                    <label class="login__remember"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured))>Feature at the top of the blog</label>
                @endif
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
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="file-input" aria-label="Cover photo">
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

@extends('layouts.unit', ['title' => 'Social media', 'active' => 'social'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Social media</h1>
            <span class="page-head__sub">Write once, post everywhere. {{ $canLead ? 'You approve and publish.' : 'Your HOD approves before anything goes out.' }}</span>
        </div>
    </header>

    <p class="box__note" style="margin: 0">
        Facebook Page: <strong>{{ $connected['facebook'] ? 'connected' : 'not connected' }}</strong> · Instagram: <strong>{{ $connected['instagram'] ? 'connected' : 'not connected' }}</strong>.
        @unless ($connected['facebook'] && $connected['instagram']) An administrator connects them in Settings &amp; roles. YouTube, X, TikTok and WhatsApp get a share kit: the caption to copy and the file to download.@endunless
    </p>

    <section class="box">
        <h2>New post</h2>
        <form method="POST" action="{{ route('unit.social.store', $unit) }}" enctype="multipart/form-data" class="form">
            @csrf
            <label class="field">Caption
                <textarea name="caption" required maxlength="2200" style="height: 130px" placeholder="What's happening? Add hashtags and links here.">{{ old('caption') }}</textarea>
                @error('caption') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">Photo or video (optional for Facebook, needed for Instagram)
                <input type="file" name="media" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime">
                @error('media') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                <legend style="margin-bottom: 6px">Post to</legend>
                <div style="display: flex; flex-wrap: wrap; gap: 6px 18px">
                    @foreach (\App\Models\SocialPost::PLATFORMS as $key => $label)
                        <label class="login__remember"><input type="checkbox" name="platforms[]" value="{{ $key }}" @checked(in_array($key, old('platforms', ['facebook', 'instagram']), true))>{{ $label }}{{ in_array($key, \App\Models\SocialPost::AUTOMATIC, true) ? '' : ' (share kit)' }}</label>
                    @endforeach
                </div>
                @error('platforms') <span class="field-error">{{ $message }}</span> @enderror
            </fieldset>
            <label class="field" style="max-width: 280px">Publish at (optional)
                <input type="datetime-local" name="publish_at" value="{{ old('publish_at') }}">
                @error('publish_at') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <div style="display: flex; gap: 8px; flex-wrap: wrap">
                <button type="submit" name="intent" value="draft" class="btn btn--ghost">Save draft</button>
                @if ($canLead)
                    <button type="submit" name="intent" value="publish" class="btn btn--gold">Publish (or schedule)</button>
                @else
                    <button type="submit" name="intent" value="submit" class="btn btn--gold">Send to HOD for approval</button>
                @endif
            </div>
        </form>
    </section>

    <nav class="tabs" aria-label="Posts">
        @foreach (['queue' => 'Waiting and scheduled', 'published' => 'Published', 'drafts' => 'Drafts'] as $key => $label)
            <a href="{{ route('unit.social.index', [$unit, 'tab' => $key]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($posts as $post)
            <a href="{{ route('unit.social.show', [$unit, $post]) }}" class="list-item" style="color: var(--ink)">
                @if ($post->media_type === 'image')
                    <img src="{{ $post->mediaUrl() }}" alt="" style="width: 56px; height: 56px; object-fit: cover; border-radius: 12px">
                @elseif ($post->media_type === 'video')
                    <span class="avatar" aria-hidden="true">▶</span>
                @endif
                <span class="list-item__main">
                    <span class="list-item__title">{{ \Illuminate\Support\Str::limit($post->caption, 90) }}</span>
                    <span class="list-item__meta">{{ collect($post->platforms)->map(fn ($p) => \App\Models\SocialPost::PLATFORMS[$p] ?? $p)->join(', ') }} · {{ $post->author?->name }}{{ $post->publish_at && ! $post->published_at ? ' · goes out '.$post->publish_at->format('D j M, g:i A') : '' }}</span>
                </span>
                <span class="pill {{ match ($post->status) { 'published' => 'pill--ok', 'failed', 'partly_published' => 'pill--warn', 'awaiting_approval' => 'pill--gold', default => 'pill--info' } }}">{{ $post->statusLabel() }}</span>
            </a>
        @empty
            <p class="empty-state">Nothing here.</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $posts])
    </section>
@endsection

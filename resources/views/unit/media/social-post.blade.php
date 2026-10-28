@extends('layouts.unit', ['title' => 'Post', 'active' => 'social'])

@php
    $share = [
        'youtube' => ['label' => 'Open YouTube Studio', 'url' => 'https://studio.youtube.com/'],
        'x' => ['label' => 'Post on X', 'url' => 'https://x.com/intent/post?text='.rawurlencode(\Illuminate\Support\Str::limit($post->caption, 270, ''))],
        'tiktok' => ['label' => 'Open TikTok upload', 'url' => 'https://www.tiktok.com/upload'],
        'whatsapp' => ['label' => 'Share on WhatsApp', 'url' => 'https://wa.me/?text='.rawurlencode($post->caption)],
    ];
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('unit.social.index', $unit) }}" class="box__link">← Social media</a>
            <h1>{{ $post->statusLabel() }}</h1>
            <span class="page-head__sub">By {{ $post->author?->name ?? 'someone' }} · {{ $post->created_at->format('j M, g:i A') }}{{ $post->approver ? ' · approved by '.$post->approver->name : '' }}{{ $post->published_at ? ' · published '.$post->published_at->format('j M, g:i A') : '' }}</span>
        </div>
        <div class="actions">
            @if ($post->status === 'draft')
                <form method="POST" action="{{ route('unit.social.act', [$unit, $post]) }}">
                    @csrf
                    <input type="hidden" name="intent" value="{{ $canLead ? 'publish' : 'submit' }}">
                    <button type="submit" class="btn btn--gold">{{ $canLead ? 'Publish' : 'Send for approval' }}</button>
                </form>
            @elseif ($post->status === 'awaiting_approval' && $canLead)
                <form method="POST" action="{{ route('unit.social.act', [$unit, $post]) }}">
                    @csrf
                    <input type="hidden" name="intent" value="reject">
                    <button type="submit" class="btn btn--ghost">Send back</button>
                </form>
                <form method="POST" action="{{ route('unit.social.act', [$unit, $post]) }}">
                    @csrf
                    <input type="hidden" name="intent" value="publish">
                    <button type="submit" class="btn btn--gold">{{ $post->publish_at?->isFuture() ? 'Approve for '.$post->publish_at->format('D j M, g:i A') : 'Approve and publish' }}</button>
                </form>
            @elseif (in_array($post->status, ['failed', 'partly_published']) && $canLead)
                <form method="POST" action="{{ route('unit.social.act', [$unit, $post]) }}">
                    @csrf
                    <input type="hidden" name="intent" value="retry">
                    <button type="submit" class="btn btn--gold">Try again</button>
                </form>
            @endif
            @if (in_array($post->status, ['draft', 'awaiting_approval', 'scheduled']))
                <form method="POST" action="{{ route('unit.social.destroy', [$unit, $post]) }}" data-confirm="Delete this post?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn--danger btn--xs">Delete</button>
                </form>
            @endif
        </div>
    </header>

    <div class="row">
        <section class="box grow">
            @if ($post->media_type === 'image')
                <img src="{{ $post->mediaUrl() }}" alt="The post's photo" style="max-width: 100%; max-height: 420px; border-radius: 16px; object-fit: contain; align-self: flex-start">
            @elseif ($post->media_type === 'video')
                <video src="{{ $post->mediaUrl() }}" controls style="max-width: 100%; max-height: 420px; border-radius: 16px"></video>
            @endif
            <p style="margin: 0; white-space: pre-line; font-size: 15px; line-height: 1.6">{{ $post->caption }}</p>
            <div style="display: flex; gap: 8px; flex-wrap: wrap">
                <button type="button" class="btn btn--ghost btn--xs" data-copy="{{ $post->caption }}">Copy caption</button>
                @if ($post->media_path)<a href="{{ $post->mediaUrl() }}" class="btn btn--ghost btn--xs" download>Download {{ $post->media_type }}</a>@endif
            </div>
        </section>

        <section class="box side-col">
            <h2>Platforms</h2>
            @foreach ($post->platforms as $platform)
                @php($result = $post->results[$platform] ?? null)
                <div class="stack" style="gap: 4px">
                    <strong style="font-size: 14px">{{ \App\Models\SocialPost::PLATFORMS[$platform] ?? $platform }}</strong>
                    @if (in_array($platform, \App\Models\SocialPost::AUTOMATIC, true))
                        @if (! $result)
                            <span class="box__note" style="margin: 0">{{ $post->status === 'scheduled' ? 'Waiting to go out' : 'Not published yet' }}</span>
                        @elseif ($result['status'] === 'published')
                            <span class="pill pill--ok" style="align-self: flex-start">Published</span>
                            @isset($result['url'])<a href="{{ $result['url'] }}" class="box__link" target="_blank" rel="noopener">View post</a>@endisset
                        @elseif ($result['status'] === 'processing')
                            <span class="pill pill--info" style="align-self: flex-start">Processing the video</span>
                        @else
                            <span class="pill pill--warn" style="align-self: flex-start">Failed</span>
                            <span class="box__note" style="margin: 0">{{ $result['error'] ?? '' }}</span>
                        @endif
                    @else
                        <span class="box__note" style="margin: 0">Post by hand: copy the caption{{ $post->media_path ? ', download the file' : '' }}, then</span>
                        <a href="{{ $share[$platform]['url'] }}" class="btn btn--ghost btn--xs" style="align-self: flex-start" target="_blank" rel="noopener">{{ $share[$platform]['label'] }}</a>
                    @endif
                </div>
            @endforeach
        </section>
    </div>
@endsection

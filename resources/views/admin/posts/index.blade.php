@extends('layouts.admin', ['title' => 'Blog', 'active' => 'blog'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Blog</h1>
            <span class="page-head__sub">Write, schedule and publish articles for the Blog page and the home page.</span>
        </div>
        <div class="actions">
            <a href="{{ route('blog.index') }}" class="btn btn--outline">View blog</a>
            <a href="{{ route('admin.posts.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />New article</a>
        </div>
    </header>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Filter articles">
            <a href="{{ route('admin.posts.index', array_filter(['q' => $search])) }}" class="tab" @if ($show === 'all') aria-current="page" @endif>All</a>
            <a href="{{ route('admin.posts.index', array_filter(['show' => 'published', 'q' => $search])) }}" class="tab" @if ($show === 'published') aria-current="page" @endif>Live <span>{{ $counts['published'] }}</span></a>
            <a href="{{ route('admin.posts.index', array_filter(['show' => 'scheduled', 'q' => $search])) }}" class="tab" @if ($show === 'scheduled') aria-current="page" @endif>Scheduled <span>{{ $counts['scheduled'] }}</span></a>
            <a href="{{ route('admin.posts.index', array_filter(['show' => 'draft', 'q' => $search])) }}" class="tab" @if ($show === 'draft') aria-current="page" @endif>Drafts <span>{{ $counts['draft'] }}</span></a>
        </nav>
        <form method="GET" action="{{ route('admin.posts.index') }}" role="search" class="search-inline">
            @if ($show !== 'all') <input type="hidden" name="show" value="{{ $show }}"> @endif
            <label class="sr-only" for="post-q">Search titles</label>
            <input id="post-q" type="search" name="q" value="{{ $search }}" placeholder="Search titles" maxlength="100">
            <button type="submit" class="btn btn--ghost">Search</button>
        </form>
    </div>

    <section class="box box--flush">
        @forelse ($posts as $post)
            <div class="list-item">
                @if ($post->thumbUrl())
                    <img src="{{ $post->thumbUrl() }}" alt="" class="list-thumb" width="64" height="48" loading="lazy">
                @else
                    <span class="list-thumb bg-{{ $post->swatch }}" aria-hidden="true"></span>
                @endif
                <span class="list-item__main">
                    <a href="{{ route('admin.posts.edit', $post) }}" class="list-item__title">{{ $post->title }}</a>
                    <span class="list-item__meta">{{ $post->topic }} · {{ $post->authorName() }} · {{ $post->readingTime() }}
                        @if ($post->status() === 'scheduled') · goes live {{ $post->published_at->format('j M Y, g:i A') }}
                        @elseif ($post->published_at) · {{ $post->published_at->format('j M Y') }}
                        @else · edited {{ $post->updated_at->diffForHumans() }} @endif
                    </span>
                </span>
                @if ($post->is_featured) <span class="pill pill--gold">Featured</span> @endif
                @switch($post->status())
                    @case('draft') <span class="pill pill--draft">Draft</span> @break
                    @case('scheduled') <span class="pill pill--scheduled">Scheduled</span> @break
                    @default <span class="pill pill--live">Live</span>
                @endswitch
                <span class="list-item__actions">
                    <a href="{{ $post->isLive() ? $post->url() : route('admin.posts.preview', $post) }}" class="btn btn--ghost btn--xs" target="_blank">{{ $post->isLive() ? 'View' : 'Preview' }}<span class="sr-only"> {{ $post->title }}</span></a>
                    <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn--ghost btn--xs">Edit<span class="sr-only"> {{ $post->title }}</span></a>
                </span>
            </div>
        @empty
            <p class="empty-state">{{ $search !== '' ? 'No articles match that search.' : 'No articles here yet.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $posts])
    </section>
@endsection

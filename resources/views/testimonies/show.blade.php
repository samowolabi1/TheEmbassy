@extends('layouts.app', [
    'title' => $testimony->title.' · Testimonies',
    'active' => null,
    'description' => $testimony->excerpt(150),
])

@section('content')
    <article class="container section--tight stack testimony-page" style="gap: 20px">
        <a href="{{ route('testimonies.index') }}" class="back-link"><x-icon name="chevron-left" :size="16" />Testimonies</a>
        <span class="eyebrow">{{ mb_strtoupper($testimony->categoryLabel()) }}</span>
        <h1 class="display testimony-page__title">{{ $testimony->title }}</h1>
        <p class="testimony-page__by">{{ $testimony->byline() }} · {{ $testimony->published_at->format('j F Y') }}</p>
        <div class="testimony-page__body">
            @foreach (preg_split("/\R{2,}/", trim($testimony->body)) as $paragraph)
                <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
        </div>

        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap">
            @auth
                <form method="POST" action="{{ route('testimonies.praise', $testimony) }}">
                    @csrf
                    <button type="submit" class="btn {{ $praised ? 'btn--outline' : 'btn--gold' }}" @disabled($praised)>🙌 {{ $praised ? 'You praised God for this' : 'Praise God' }}</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn--gold">🙌 Praise God</a>
            @endauth
            @if ($testimony->praises)
                <span class="field-hint">{{ number_format($testimony->praises) }} {{ \Illuminate\Support\Str::plural('person', $testimony->praises) }} praised God for this</span>
            @endif
            <button type="button" class="btn btn--outline btn--sm" data-copy="{{ route('testimonies.show', $testimony) }}" data-copy-label="Copy link">Copy link</button>
            <a href="https://wa.me/?text={{ rawurlencode($testimony->title.' · '.route('testimonies.show', $testimony)) }}" target="_blank" rel="noopener" class="btn btn--outline btn--sm">Share on WhatsApp</a>
        </div>
    </article>

    @if ($more->isNotEmpty())
        <section class="container section--tight section--last stack" style="gap: 20px">
            <h2 class="h2 h2--sm" style="margin: 0">More testimonies</h2>
            <div class="testimony-grid">
                @foreach ($more as $t)
                    <a href="{{ route('testimonies.show', $t) }}" class="testimony-card">
                        <span class="testimony-card__tag">{{ $t->categoryLabel() }}</span>
                        <span class="testimony-card__title">{{ $t->title }}</span>
                        <span class="testimony-card__text">{{ $t->excerpt(160) }}</span>
                        <span class="testimony-card__by">{{ $t->byline() }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection

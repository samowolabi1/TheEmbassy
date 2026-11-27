@extends('layouts.app', ['title' => 'My saved verses', 'active' => null, 'robots' => 'noindex'])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account.verses') }}" class="back-link"><x-icon name="chevron-left" :size="16" />Verses</a>
        <span class="eyebrow">MY SAVED VERSES</span>
        <h1 class="display notices-hero__title">Words to hold on to.</h1>
    </section>

    <section class="container section--tight section--last stack" style="gap: 14px">
        @forelse ($verses as $v)
            <article class="card saved-verse">
                <blockquote class="saved-verse__text">{{ $v->text }}</blockquote>
                <div class="saved-verse__foot">
                    <cite class="saved-verse__ref">{{ $v->reference }} · KJV</cite>
                    <form method="POST" action="{{ route('account.verses.save', $v) }}" data-confirm="Remove {{ $v->reference }} from your saved verses?" data-confirm-ok="Remove">
                        @csrf
                        <button type="submit" class="btn btn--outline btn--sm">Remove</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="card stack" style="gap: 12px">
                <p style="margin: 0">No saved verses yet. Tap <strong>♡ Save</strong> on a verse that speaks to you, and it will be kept here.</p>
                <a href="{{ route('account.verses') }}" class="btn btn--gold" style="align-self: flex-start">Read verses</a>
            </div>
        @endforelse
    </section>
@endsection

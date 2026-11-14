@extends('layouts.app', ['title' => $book->title.' · Library', 'active' => null])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@php($avg = $reviews->avg('rating'))

@section('content')
    <section class="container section--tight stack" style="gap: 24px; padding-top: 32px">
        <a href="{{ route('library.index') }}" class="back-link"><x-icon name="chevron-left" :size="16" />Library</a>
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="notice-alert" role="alert" style="margin: 0">{{ $errors->first() }}</p>
        @endif

        <div class="book-detail">
            <div class="book-detail__cover">@include('library.cover', ['book' => $book])</div>
            <div class="stack" style="gap: 14px">
                <span class="tag tag--wash" style="align-self: flex-start">{{ $book->categoryLabel() }}</span>
                <h1 class="h2" style="margin: 0">{{ $book->title }}</h1>
                <p class="lede" style="margin: 0">by {{ $book->author }}</p>
                @if ($avg)
                    <p style="margin: 0; font-weight: 700"><span class="stars">{{ str_repeat('★', (int) round($avg)) }}{{ str_repeat('☆', 5 - (int) round($avg)) }}</span> {{ number_format($avg, 1) }} from {{ $reviews->count() }} {{ \Illuminate\Support\Str::plural('review', $reviews->count()) }}</p>
                @endif
                @if ($book->description)
                    <p style="margin: 0; white-space: pre-line; line-height: 1.7">{{ $book->description }}</p>
                @endif
                <p class="field-hint" style="margin: 0">
                    {{ $book->copies }} {{ \Illuminate\Support\Str::plural('copy', $book->copies) }} ·
                    {{ $free > 0 ? $free.' available' : 'all out' }}{{ $queue ? ' · '.$queue.' waiting' : '' }}{{ $book->shelf ? ' · '.$book->shelf : '' }}
                </p>

                @if ($myLoan)
                    <div class="book-status">
                        <strong>{{ $myLoan->label() }}</strong>
                        <span>
                            @switch($myLoan->status)
                                @case('on_loan') {{ $myLoan->isOverdue() ? 'It was due back' : 'Due back' }} {{ $myLoan->due_on->format('l j M') }}. @if (! $myLoan->isOverdue() && $returnTokens) Bring it back by then to earn {{ $returnTokens }} tokens.@endif @break
                                @case('requested') A copy is set aside for you. Collect it from the library at church within {{ config('church.library.collect_days', 7) }} days. @break
                                @case('waiting') You're number {{ $myLoan->queuePosition() }} on the waiting list. We'll tell you when a copy is free. @break
                            @endswitch
                        </span>
                    </div>
                @elseif ($canBorrow)
                    <form method="POST" action="{{ route('library.reserve', $book) }}">
                        @csrf
                        <button type="submit" class="btn btn--gold">{{ $free > 0 && ! $queue ? 'Reserve this book' : 'Join the waiting list' }}</button>
                    </form>
                @else
                    <p class="field-hint" style="margin: 0">Church members can borrow books.</p>
                @endif
            </div>
        </div>
    </section>

    <section class="container section--tight section--last stack" style="gap: 16px" id="reviews">
        <h2 class="h2 h2--sm" style="margin: 0">Reviews</h2>
        @if ($canBorrow)
            <form method="POST" action="{{ route('community.reviews.store', $book) }}" class="card notice-form__main" style="flex: none" id="review">
                @csrf
                <fieldset class="notice-form__group">
                    <legend>{{ $myReview ? 'Your review' : ($borrowedBefore ? 'What did you think?' : 'Read it? Review it') }}</legend>
                    <div class="star-input" role="radiogroup" aria-label="Rating">
                        @foreach (range(5, 1) as $n)
                            <input type="radio" name="rating" value="{{ $n }}" id="star-{{ $n }}" @checked((int) old('rating', $myReview?->rating) === $n)>
                            <label for="star-{{ $n }}" title="{{ $n }} {{ \Illuminate\Support\Str::plural('star', $n) }}">★</label>
                        @endforeach
                    </div>
                    @error('rating') <span class="field-error">{{ $message }}</span> @enderror
                    <label class="field">Your thoughts
                        <textarea name="body" required minlength="10" maxlength="2000" rows="4" placeholder="What stood out to you? Who would you recommend it to?">{{ old('body', $myReview?->body) }}</textarea>
                        @error('body') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <button type="submit" class="btn btn--navy" style="align-self: flex-start">{{ $myReview ? 'Update review' : 'Post review' }}</button>
                    <span class="field-hint">Reviews show here and in the community's Book reviews. @if (! $myReview && $reviewTokens) Your first review of a book earns {{ $reviewTokens }} tokens.@endif</span>
                </fieldset>
            </form>
        @endif
        @forelse ($reviews as $r)
            <article class="card review-card">
                <div class="review-card__head">
                    <strong>{{ $r->member->name }}</strong>
                    <span class="stars" aria-label="{{ $r->rating }} out of 5">{{ $r->stars() }}</span>
                    <span class="field-hint">{{ $r->created_at->diffForHumans() }}</span>
                </div>
                <p style="margin: 0; white-space: pre-line">{{ $r->body }}</p>
                @if ($r->member_id === auth()->user()->member?->id || auth()->user()->moderatesCommunity())
                    <form method="POST" action="{{ route('community.reviews.destroy', $r) }}" onsubmit="return confirm('{{ $r->member_id === auth()->user()->member?->id ? 'Delete your review?' : 'Take this review down?' }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--outline btn--sm">{{ $r->member_id === auth()->user()->member?->id ? 'Delete' : 'Take down' }}</button>
                    </form>
                @endif
            </article>
        @empty
            <p class="lede" style="margin: 0">No reviews yet.</p>
        @endforelse
    </section>
@endsection

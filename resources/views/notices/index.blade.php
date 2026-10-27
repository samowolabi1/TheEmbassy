@extends('layouts.app', [
    'title' => 'Notice board',
    'description' => 'Events, businesses, jobs and services from churches and businesses in our community.',
])

@section('content')
    <section class="container notices-hero stack" style="gap: 28px">
        <div class="section-head">
            <div class="stack" style="gap: 14px; max-width: 720px">
                <span class="eyebrow">NOTICE BOARD</span>
                <h1 class="display notices-hero__title">What's happening <span class="accent-bronze">around us.</span></h1>
                <p class="lede">Events, businesses, jobs and services from churches and businesses in our community.</p>
            </div>
            <a href="{{ route('notices.create') }}" class="btn btn--gold">Place a notice</a>
        </div>
    </section>

    <section class="container section--tight section--last stack" style="gap: 24px" data-filter-scope>
        @if ($notices->isEmpty())
            <div class="card notices-empty">
                <p class="lede" style="margin: 0">There are no notices up right now.</p>
                <a href="{{ route('notices.create') }}" class="btn btn--navy btn--md">Be the first to advertise</a>
            </div>
        @else
            <div role="tablist" aria-label="Filter notices" class="filters">
                <button type="button" role="tab" class="filter" aria-selected="true" data-filter="all">All</button>
                @foreach ($categories as $category)
                    @if ($notices->contains('category', $category))
                        <button type="button" role="tab" class="filter" aria-selected="false" data-filter="{{ $category }}">{{ $category }}</button>
                    @endif
                @endforeach
            </div>
            <div class="grid-auto" style="--min: 320px; --gap: 22px">
                @foreach ($notices as $notice)
                    @include('notices.card', ['notice' => $notice])
                @endforeach
            </div>
        @endif
        <p class="notices-disclaimer">Notices are paid listings from other churches and businesses. They are reviewed before they appear, but {{ config('church.short_name') }} doesn't endorse what they offer.</p>
    </section>
@endsection

@extends('layouts.app', [
    'title' => config('church.brand.market_name'),
    'active' => 'market',
    'description' => 'Shop from businesses run by members of '.config('church.short_name').'.',
])

@section('content')
    <section class="container notices-hero stack" style="gap: 28px">
        <div class="section-head">
            <div class="stack" style="gap: 14px; max-width: 720px">
                <span class="eyebrow">{{ strtoupper(config('church.brand.market_name')) }}</span>
                <h1 class="display notices-hero__title">Shop from <span class="accent-bronze">the family.</span></h1>
                <p class="lede">Products and services from members' businesses. Every order supports a member and the work of the church.</p>
            </div>
            <a href="{{ route('market.apply') }}" class="btn btn--outline">Open a shop</a>
        </div>
    </section>

    <section class="container section--tight stack" style="gap: 24px" data-filter-scope>
        @if ($products->isEmpty())
            <div class="card notices-empty">
                <p class="lede" style="margin: 0">The market is just getting started. Check back soon.</p>
                <a href="{{ route('market.apply') }}" class="btn btn--navy btn--md">Sell something</a>
            </div>
        @else
            @if ($products->pluck('type')->unique()->count() > 1)
                <div role="tablist" aria-label="Products or services" class="filters">
                    <button type="button" role="tab" class="filter" aria-selected="true" data-filter="all">All</button>
                    <button type="button" role="tab" class="filter" aria-selected="false" data-filter="product">Products</button>
                    <button type="button" role="tab" class="filter" aria-selected="false" data-filter="service">Services</button>
                </div>
            @endif
            <div class="grid-auto" style="--min: 230px; --gap: 22px 18px">
                @foreach ($products as $product)
                    @include('market.product-card', ['product' => $product])
                @endforeach
            </div>
        @endif
    </section>

    @if ($shops->isNotEmpty())
        <section class="container section--tight section--last stack" style="gap: 20px">
            <h2 class="h2 h2--sm">Shops</h2>
            <div class="grid-auto" style="--min: 260px; --gap: 14px">
                @foreach ($shops as $shop)
                    <a href="{{ route('market.shop', $shop) }}" class="card shop-card">
                        <span class="shop-card__mark">{{ \Illuminate\Support\Str::of($shop->name)->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}</span>
                        <span class="stack" style="gap: 2px; min-width: 0">
                            <span class="product__name">{{ $shop->name }}</span>
                            <span class="product__seller">{{ $shop->category }} · {{ $shop->products_count }} {{ \Illuminate\Support\Str::plural('item', $shop->products_count) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection

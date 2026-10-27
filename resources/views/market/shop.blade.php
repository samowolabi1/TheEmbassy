@extends('layouts.app', [
    'title' => $shop->name,
    'active' => 'market',
    'description' => \Illuminate\Support\Str::limit($shop->description, 150),
])

@section('content')
    <section class="container notices-hero stack" style="gap: 14px">
        <a href="{{ route('market.index') }}" class="back-link"><x-icon name="chevron-left" :size="16" />{{ config('church.brand.market_name') }}</a>
        <span class="tag tag--wash">{{ $shop->category }}</span>
        <h1 class="display notices-hero__title">{{ $shop->name }}</h1>
        <p class="lede" style="max-width: 680px; white-space: pre-line">{{ $shop->description }}</p>
        <span class="product__seller">Run by {{ $shop->owner_name }}</span>
        @unless ($shop->isListed())
            <p class="contact-note" role="status" style="max-width: 680px">Only you can see this page. Your shop shows in the market while your subscription is active. <a href="{{ route('vendor.subscription.index') }}" style="font-weight: 800">Subscribe</a></p>
        @endunless
    </section>

    <section class="container section--tight section--last">
        @if ($products->isEmpty())
            <p class="lede">Nothing listed yet.</p>
        @else
            <div class="grid-auto" style="--min: 230px; --gap: 22px 18px">
                @foreach ($products as $product)
                    @include('market.product-card', ['product' => $product->setRelation('shop', $shop)])
                @endforeach
            </div>
        @endif
    </section>
@endsection

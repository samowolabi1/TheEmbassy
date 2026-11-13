@extends('layouts.vendor', ['title' => 'Share', 'active' => 'share'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Share your shop</h1>
            <span class="page-head__sub">Ready-made pictures and captions for WhatsApp, Instagram, Facebook, X and Telegram. Each picture has your price and a QR code, and every link opens your page in the {{ config('church.brand.market_name') }}. Visits from your shared links so far: {{ number_format($visits) }}.</span>
        </div>
    </header>

    @unless ($shop->isListed())
        <p class="flash flash--error">Your shop isn't in the market yet, so people who open your links won't see it. <a href="{{ route('vendor.subscription.index') }}">Subscribe</a> to go live, then share.</p>
    @endunless

    <section class="box">
        <h2>Tips</h2>
        <ul class="box__note" style="margin: 0; padding-left: 18px; display: grid; gap: 4px">
            <li><strong>WhatsApp status and Instagram stories:</strong> on your phone, tap "Share…" to send the picture straight to the app, or download the "Status and stories" picture and post it. Put the link in your status caption.</li>
            <li><strong>WhatsApp groups and chats:</strong> tap WhatsApp. The link shows a preview with your photo and price.</li>
            <li><strong>Flyers and your stall:</strong> download the QR code. People scan it to open your page.</li>
        </ul>
    </section>

    <section class="box" id="shop">
        <h2>Your shop: {{ $shop->name }}</h2>
        @include('vendor.share-kit', ['item' => $shop, 'kind' => 'shop', 'id' => 'shop'])
    </section>

    @forelse ($products as $product)
        <section class="box" id="product-{{ $product->slug }}">
            <h2>{{ $product->name }} <span class="field__hint" style="font-weight: 600">{{ $product->priceLabel() }}</span></h2>
            @include('vendor.share-kit', ['item' => $product, 'kind' => 'product', 'id' => 'p'.$product->id])
        </section>
    @empty
        <section class="box"><p class="empty-state">Add a listing to share it here.</p></section>
    @endforelse
@endsection

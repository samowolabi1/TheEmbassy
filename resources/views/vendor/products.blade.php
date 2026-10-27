@extends('layouts.vendor', ['title' => 'Products & services', 'active' => 'products'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Products &amp; services</h1>
            <span class="page-head__sub">What {{ $shop->name }} offers. Hidden listings don't show in the market.</span>
        </div>
        <div class="actions">
            <a href="{{ route('vendor.products.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />Add a listing</a>
        </div>
    </header>

    <section class="box box--flush">
        @forelse ($products as $product)
            <div class="list-item">
                @if ($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="" style="width: 64px; height: 64px; object-fit: cover; border-radius: 12px">
                @endif
                <span class="list-item__main">
                    <span class="list-item__title">{{ $product->name }}</span>
                    <span class="list-item__meta">{{ $product->isService() ? 'Service' : 'Product' }} · {{ $product->priceLabel() }}@unless ($product->isService()) · {{ $product->stock === null ? 'No stock limit' : $product->stock.' in stock' }}@endunless</span>
                </span>
                @unless ($product->is_active)
                    <span class="pill pill--draft">Hidden</span>
                @endunless
                @if ($product->stock === 0)
                    <span class="pill pill--warn">Sold out</span>
                @endif
                <span class="list-item__actions">
                    <a href="{{ route('vendor.products.edit', $product) }}" class="btn btn--ghost btn--xs">Edit<span class="sr-only"> {{ $product->name }}</span></a>
                </span>
            </div>
        @empty
            <p class="empty-state">Nothing listed yet. Add your first product or service.</p>
        @endforelse
    </section>
@endsection

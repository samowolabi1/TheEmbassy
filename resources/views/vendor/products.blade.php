@extends('layouts.vendor', ['title' => 'Products & services', 'active' => 'products'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Products &amp; services</h1>
            <span class="page-head__sub">What {{ $shop->name }} offers. Featured listings show first in your shop; hidden ones don't show anywhere.</span>
        </div>
        <div class="actions">
            <a href="{{ route('vendor.products.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />Add a listing</a>
        </div>
    </header>

    @if ($justAdded)
        <section class="box" id="share-new" style="border: 2px solid var(--gold)">
            <h2>Share "{{ $justAdded->name }}" 📣</h2>
            <p class="box__note" style="margin: 0">Tell your customers it's here. Each link opens the listing with its photos and price.@unless ($shop->isListed()) It shows to buyers once you subscribe.@endunless</p>
            @include('market.share-bar', ['item' => $justAdded, 'cards' => $cards, 'image' => route('market.product.image', [$justAdded, 'square'])])
        </section>
    @endif

    @if ($errors->has('product'))<p class="flash" role="alert" style="background: #fdecea; color: #8a1c12">{{ $errors->first('product') }}</p>@endif

    @if ($collections->isNotEmpty())
        <nav class="tabs" aria-label="Sections">
            <a href="{{ route('vendor.products.index') }}" class="tab" @if ($section === null) aria-current="page" @endif>All</a>
            @foreach ($collections as $c)
                <a href="{{ route('vendor.products.index', ['section' => $c->id]) }}" class="tab" @if ((string) $section === (string) $c->id) aria-current="page" @endif>{{ $c->name }}</a>
            @endforeach
            <a href="{{ route('vendor.products.index', ['section' => 'none']) }}" class="tab" @if ($section === 'none') aria-current="page" @endif>No section</a>
        </nav>
    @endif

    <section class="box box--flush">
        @forelse ($products as $product)
            <div class="list-item">
                @if ($product->thumbUrl())
                    <img src="{{ $product->thumbUrl() }}" alt="" style="width: 64px; height: 64px; object-fit: cover; border-radius: 12px; flex: none">
                @else
                    <span style="width: 64px; height: 64px; border-radius: 12px; flex: none; background: var(--sand); display: grid; place-items: center; font-size: 11px; color: var(--slate)">No photo</span>
                @endif
                <span class="list-item__main">
                    <span class="list-item__title">{{ $product->name }}</span>
                    <span class="list-item__meta">{{ $product->isService() ? 'Service' : 'Product' }} · {{ $product->priceLabel() }}@if ($product->onSale()) <s>{{ $product->compareLabel() }}</s>@endif @if ($product->collection) · {{ $product->collection->name }}@endif · {{ $product->images->count() }} {{ \Illuminate\Support\Str::plural('photo', $product->images->count()) }}@unless ($product->isService()) · {{ $product->stock === null ? 'No stock limit' : $product->stock.' in stock' }}@endunless</span>
                </span>
                @if ($product->is_featured)<span class="pill pill--info">Featured</span>@endif
                @if ($product->onSale())<span class="pill pill--ok">{{ $product->salePercent() }}% off</span>@endif
                @unless ($product->is_active)
                    <span class="pill pill--draft">Hidden</span>
                @endunless
                @if ($product->stock === 0)
                    <span class="pill pill--warn">Sold out</span>
                @endif
                <span class="list-item__actions">
                    @if ($product->is_active)
                        <a href="{{ route('vendor.share.index') }}#product-{{ $product->slug }}" class="btn btn--ghost btn--xs">Share<span class="sr-only"> {{ $product->name }}</span></a>
                    @endif
                    <form method="POST" action="{{ route('vendor.products.toggle', $product) }}">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--xs">{{ $product->is_active ? 'Hide' : 'Show' }}<span class="sr-only"> {{ $product->name }}</span></button>
                    </form>
                    <a href="{{ route('vendor.products.edit', $product) }}" class="btn btn--ghost btn--xs">Edit<span class="sr-only"> {{ $product->name }}</span></a>
                </span>
            </div>
        @empty
            <p class="empty-state">Nothing listed yet. Add your first product or service.</p>
        @endforelse
    </section>
@endsection

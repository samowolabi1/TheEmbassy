{{-- One product in a grid. --}}
<a href="{{ route('market.product', $product) }}" class="product" data-filter-item data-tag="{{ $product->type }}{{ $product->acceptsTokens() ? ' tokens' : '' }}">
    <span class="product__media">
        @if ($product->thumbUrl())
            <img class="product__photo" src="{{ $product->thumbUrl() }}" alt="" loading="lazy">
        @else
            <span role="img" aria-label="No photo" class="product__image bg-{{ $product->id % 2 ? 'ice' : 'wash' }}"><span class="label-chip">{{ $product->isService() ? 'Service' : $product->shop->category }}</span></span>
        @endif
        @if ($product->onSale())<span class="product__badge product__badge--sale">{{ $product->salePercent() }}% off</span>@endif
        @if (! $product->inStock())<span class="product__badge">Sold out</span>@endif
        @if ($product->relationLoaded('images') && $product->images->count() > 1)<span class="product__count" aria-label="{{ $product->images->count() }} photos">{{ $product->images->count() }} photos</span>@endif
    </span>
    <span class="product__row">
        <span class="stack" style="gap: 2px; min-width: 0">
            <span class="product__name">{{ $product->name }}</span>
            @unless ($hideShop ?? false)<span class="product__seller">{{ $product->shop->name }}</span>@endunless
        </span>
        <span class="stack" style="gap: 0; align-items: flex-end; flex: none">
            <span class="product__price">{{ $product->priceLabel() }}</span>
            @if ($product->onSale())<s class="product__was">{{ $product->compareLabel() }}</s>@endif
        </span>
    </span>
    @if ($product->acceptsTokens())
        <span class="token-chip">{{ TokenName::icon() }} {{ number_format($product->token_price) }} {{ TokenName::many() }}</span>
    @endif
    @if ($product->isService() && $product->thumbUrl())
        <span class="field-hint">Service</span>
    @endif
</a>

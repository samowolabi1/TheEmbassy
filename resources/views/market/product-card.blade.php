{{-- One product in a grid. --}}
<a href="{{ route('market.product', $product) }}" class="product" data-filter-item data-tag="{{ $product->type }}">
    @if ($product->imageUrl())
        <img class="product__photo" src="{{ $product->imageUrl() }}" alt="" loading="lazy">
    @else
        <div role="img" aria-label="No photo" class="product__image bg-{{ $product->id % 2 ? 'ice' : 'wash' }}"><span class="label-chip">{{ $product->isService() ? 'Service' : $product->shop->category }}</span></div>
    @endif
    <span class="product__row">
        <span class="stack" style="gap: 2px">
            <span class="product__name">{{ $product->name }}</span>
            <span class="product__seller">{{ $product->shop->name }}</span>
        </span>
        <span class="product__price">{{ $product->priceLabel() }}</span>
    </span>
    @if ($product->isService() && $product->imageUrl())
        <span class="field-hint">Service</span>
    @elseif (! $product->inStock())
        <span class="field-hint">Sold out</span>
    @endif
</a>

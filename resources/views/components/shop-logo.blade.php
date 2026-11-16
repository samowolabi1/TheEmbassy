{{-- A shop's logo, or its default: the shop's initials on its colour with a small crown. --}}
@props(['shop', 'size' => 48])
@if ($shop->logoUrl())
    <img src="{{ $shop->logoUrl() }}" alt="{{ $shop->name }} logo" width="{{ $size }}" height="{{ $size }}" {{ $attributes->class('shop-logo') }} style="--size: {{ $size }}px" loading="lazy">
@else
    <span {{ $attributes->class('shop-logo shop-logo--default') }} style="--size: {{ $size }}px; --shop: {{ $shop->colours()[0] }}; --shop-soft: {{ $shop->colours()[1] }}" role="img" aria-label="{{ $shop->name }}">
        <svg class="shop-logo__crown" viewBox="0 0 24 14" aria-hidden="true"><path d="M2 12 1 3l6 4 5-6 5 6 6-4-1 9z" fill="currentColor"/></svg>
        <span class="shop-logo__initials">{{ $shop->initials() }}</span>
    </span>
@endif

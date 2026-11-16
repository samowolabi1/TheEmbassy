{{-- A shop's banner, or its default: the shop's colours with a soft crown pattern, its name and tagline. --}}
@props(['shop', 'preview' => false])
@if ($shop->bannerUrl())
    <div {{ $attributes->class('shop-banner') }} style="background-image: url('{{ $shop->bannerUrl() }}')" role="img" aria-label="{{ $shop->name }} banner" @if ($preview) data-preview-banner @endif></div>
@else
    <div {{ $attributes->class('shop-banner shop-banner--default') }} style="--shop: {{ $shop->colours()[0] }}; --shop-soft: {{ $shop->colours()[1] }}" @if ($preview) data-preview-banner @endif>
        <span class="shop-banner__words" aria-hidden="true">
            <span class="shop-banner__name">{{ $shop->name }}</span>
            <span class="shop-banner__tag">{{ $shop->tagline ?: $shop->category }} · {{ config('church.brand.market_name') }}</span>
        </span>
    </div>
@endif

@extends('layouts.app', [
    'title' => $shop->name,
    'active' => 'market',
    'description' => ($shop->tagline ?: $shop->category).' · '.\Illuminate\Support\Str::limit($shop->description, 140),
    'image' => route('market.shop.image', [$shop, 'wide']),
])

@push('head')
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
@endpush

@section('content')
    @php
        [$main, $soft] = $shop->colours();
        $whatsapp = $shop->whatsappUrl();
        $hasInfo = $shop->about || $shop->delivery_info || $shop->returns_info || $shop->hours || $shop->address || $shop->socialLinks();
    @endphp
    <div class="storefront" style="--shop: {{ $main }}; --shop-soft: {{ $soft }}">
        @if ($shop->announcement)
            <p class="storefront__announce">{{ $shop->announcement }}</p>
        @endif

        <section class="container storefront__top">
            <a href="{{ route('market.index') }}" class="back-link"><x-icon name="chevron-left" :size="16" />{{ config('church.brand.market_name') }}</a>
            @unless ($shop->isListed())
                <p class="contact-note" role="status">Only you can see this page. Your shop shows in the market while your subscription is active. <a href="{{ route('vendor.subscription.index') }}" style="font-weight: 800">Subscribe</a></p>
            @endunless
            <div class="storefront__banner" @if ($shop->bannerUrl()) style="background-image: url('{{ $shop->bannerUrl() }}')" @endif role="img" aria-label="{{ $shop->name }} banner"></div>
            <div class="storefront__head">
                @if ($shop->logoUrl())
                    <img class="storefront__logo" src="{{ $shop->logoUrl() }}" alt="{{ $shop->name }} logo">
                @else
                    <span class="storefront__logo storefront__logo--initials" aria-hidden="true">{{ $shop->initials() }}</span>
                @endif
                <div class="storefront__title">
                    <span class="storefront__category">{{ $shop->category }}</span>
                    <h1 class="display">{{ $shop->name }}</h1>
                    <p class="storefront__tagline">{{ $shop->tagline ?: \Illuminate\Support\Str::limit($shop->description, 120) }}</p>
                    <span class="product__seller">Run by {{ $shop->owner_name }} · {{ $total }} {{ \Illuminate\Support\Str::plural('listing', $total) }}@if ($shop->hours) · {{ $shop->hours }}@endif</span>
                </div>
                <div class="storefront__actions">
                    @if ($whatsapp)<a href="{{ $whatsapp }}?text={{ rawurlencode('Hi '.$shop->owner_name.', I found '.$shop->name.' on '.config('church.brand.market_name').'.') }}" class="btn btn--shop" target="_blank" rel="noopener">Chat on WhatsApp</a>@endif
                    <a href="{{ $shop->telUrl() }}" class="btn btn--outline">Call</a>
                    @if ($hasInfo)<a href="#about" class="btn btn--outline">About</a>@endif
                </div>
            </div>
            @include('market.share-bar', ['item' => $shop, 'cards' => app(\App\Services\Market\ShareCards::class), 'image' => route('market.shop.image', [$shop, 'square'])])
        </section>

        <section class="container section--tight">
            <form method="GET" action="{{ route('market.shop', $shop) }}#shop-items" class="storefront__tools" id="shop-items">
                @if ($collections->isNotEmpty())
                    <nav class="storefront__sections" aria-label="Sections">
                        <a href="{{ route('market.shop', $shop) }}#shop-items" class="chip" @if (! $section) aria-current="page" @endif>All</a>
                        @foreach ($collections as $c)
                            <a href="{{ route('market.shop', [$shop, 'section' => $c->slug]) }}#shop-items" class="chip" @if ($section?->is($c)) aria-current="page" @endif>{{ $c->name }}</a>
                        @endforeach
                    </nav>
                @endif
                @if ($section)<input type="hidden" name="section" value="{{ $section->slug }}">@endif
                <label class="sr-only" for="shop-q">Search {{ $shop->name }}</label>
                <input id="shop-q" type="search" name="q" value="{{ $q }}" placeholder="Search this shop" class="storefront__search">
                <label class="sr-only" for="shop-sort">Sort</label>
                <select id="shop-sort" name="sort" class="storefront__sort" onchange="this.form.submit()">
                    <option value="">Sort: featured first</option>
                    <option value="newest" @selected($sort === 'newest')>Newest</option>
                    <option value="price-low" @selected($sort === 'price-low')>Price: low to high</option>
                    <option value="price-high" @selected($sort === 'price-high')>Price: high to low</option>
                </select>
                <button type="submit" class="btn btn--navy btn--sm">Search</button>
            </form>

            @if ($total === 0)
                <p class="lede">Nothing listed yet. Check back soon!</p>
            @else
                @if ($featured->isNotEmpty())
                    <h2 class="storefront__h2">Featured</h2>
                    <div class="grid-auto storefront__grid" style="--min: 220px">
                        @foreach ($featured as $product)
                            @include('market.product-card', ['product' => $product, 'hideShop' => true])
                        @endforeach
                    </div>
                @endif

                @if ($grouped)
                    @foreach ($grouped as $group)
                        <h2 class="storefront__h2" id="section-{{ $group['collection']->slug }}">{{ $group['collection']->name }} <a href="{{ route('market.shop', [$shop, 'section' => $group['collection']->slug]) }}#shop-items" class="storefront__see">See all {{ $group['products']->count() }}</a></h2>
                        <div class="grid-auto storefront__grid" style="--min: 220px">
                            @foreach ($group['products'] as $product)
                                @include('market.product-card', ['product' => $product, 'hideShop' => true])
                            @endforeach
                        </div>
                    @endforeach
                    @if ($unsorted->isNotEmpty())
                        <h2 class="storefront__h2">More from {{ $shop->name }}</h2>
                        <div class="grid-auto storefront__grid" style="--min: 220px">
                            @foreach ($unsorted as $product)
                                @include('market.product-card', ['product' => $product, 'hideShop' => true])
                            @endforeach
                        </div>
                    @endif
                @else
                    <h2 class="storefront__h2">{{ $section?->name ?? ($q !== '' ? 'Results for "'.$q.'"' : 'Everything') }} <span class="storefront__see">{{ $products->count() }} {{ \Illuminate\Support\Str::plural('listing', $products->count()) }}</span></h2>
                    @if ($products->isEmpty())
                        <p class="lede">Nothing matches. <a href="{{ route('market.shop', $shop) }}" class="accent-bronze">See everything</a></p>
                    @else
                        <div class="grid-auto storefront__grid" style="--min: 220px">
                            @foreach ($products as $product)
                                @include('market.product-card', ['product' => $product, 'hideShop' => true])
                            @endforeach
                        </div>
                    @endif
                @endif
            @endif
        </section>

        @if ($hasInfo)
            <section class="container section--tight section--last" id="about">
                <div class="storefront__info">
                    <div class="storefront__about">
                        <h2 class="storefront__h2">About {{ $shop->name }}</h2>
                        <p style="white-space: pre-line; margin: 0">{{ $shop->about ?: $shop->description }}</p>
                        @if ($links = $shop->socialLinks())
                            <div class="storefront__socials">
                                @foreach ($links as $label => $url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener nofollow" class="chip">{{ $label }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <dl class="storefront__facts">
                        @if ($shop->hours)<div><dt>Opening hours</dt><dd>{{ $shop->hours }}</dd></div>@endif
                        @if ($shop->address)<div><dt>Where to find us</dt><dd>{{ $shop->address }}<br><a href="{{ $shop->mapUrl() }}" target="_blank" rel="noopener" class="accent-bronze">Directions</a></dd></div>@endif
                        @if ($shop->delivery_info)<div><dt>Delivery</dt><dd style="white-space: pre-line">{{ $shop->delivery_info }}</dd></div>@endif
                        @if ($shop->returns_info)<div><dt>Returns and refunds</dt><dd style="white-space: pre-line">{{ $shop->returns_info }}</dd></div>@endif
                        <div><dt>Contact</dt><dd>{{ $shop->owner_name }} · <a href="{{ $shop->telUrl() }}">{{ $shop->phone }}</a>@if ($shop->cac_number)<br>CAC {{ $shop->cac_number }}@endif</dd></div>
                    </dl>
                </div>
            </section>
        @else
            <div class="section--last"></div>
        @endif
    </div>
@endsection

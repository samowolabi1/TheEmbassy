@extends('layouts.app', [
    'title' => $product->name,
    'active' => 'market',
    'description' => \Illuminate\Support\Str::limit($product->description, 150),
])

@php($shop = $product->shop)

@section('content')
    <section class="container notices-hero section--last">
        <a href="{{ route('market.shop', $shop) }}" class="back-link"><x-icon name="chevron-left" :size="16" />{{ $shop->name }}</a>
        @unless ($shop->isListed())
            <p class="contact-note" role="status" style="margin-top: 16px">Only you can see this listing until your subscription is active.</p>
        @endunless

        <div class="notice-form" style="margin-top: 20px">
            <div class="notice-form__main" style="padding: 0; background: none; border: 0; gap: 20px">
                @if ($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="product-hero-photo">
                @else
                    <div role="img" aria-label="No photo" class="product-hero-photo bg-ice"></div>
                @endif
                <div class="stack" style="gap: 12px">
                    <span class="tag tag--wash">{{ $product->isService() ? 'Service' : $shop->category }}</span>
                    <h1 class="h2">{{ $product->name }}</h1>
                    <span class="product-hero-price">{{ $product->priceLabel() }}</span>
                    <p class="lede" style="margin: 0; white-space: pre-line">{{ $product->description }}</p>
                    <span class="product__seller">Sold by <a href="{{ route('market.shop', $shop) }}" style="font-weight: 800">{{ $shop->name }}</a></span>
                </div>
            </div>

            <aside class="card notice-form__side" id="contact">
                <h2 style="margin: 0; font-family: var(--font-display); font-weight: 800; font-size: 22px; color: var(--navy)">{{ $product->isService() ? 'Book with '.$shop->owner_name : 'Order from '.$shop->owner_name }}</h2>
                @if ($product->stock === 0)
                    <span class="pill pill--warn" style="align-self: flex-start">Sold out for now</span>
                @endif
                <div class="contact-buttons">
                    <a href="{{ $shop->telUrl() }}" class="btn btn--navy">Call {{ $shop->phone }}</a>
                    @if ($whatsapp = $shop->whatsappUrl())
                        <a href="{{ $whatsapp }}?text={{ rawurlencode('Hi, I found "'.$product->name.'" on '.config('church.brand.market_name').'.') }}" class="btn btn--gold" target="_blank" rel="noopener">Chat on WhatsApp</a>
                    @endif
                    <a href="mailto:{{ $shop->email }}?subject={{ rawurlencode($product->name) }}" class="btn btn--outline">Email the seller</a>
                </div>

                @if (session('enquired'))
                    <p class="notice-alert" role="status" style="margin: 0; background: var(--wash); color: var(--navy)">Message sent. {{ session('enquired') }} will get back to you.</p>
                @else
                    <form method="POST" action="{{ route('market.enquire', $product) }}" class="notice-form__group">
                        @csrf
                        <h3 style="margin: 0; font-size: 16px; color: var(--navy)">Or send a message</h3>
                        <label class="honeypot" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        @if ($product->isService())
                            <label class="field">When do you need it?
                                <input type="date" name="preferred_date" value="{{ old('preferred_date') }}" min="{{ today()->toDateString() }}" required>
                                @error('preferred_date') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        @endif
                        <label class="field">Your name
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name">
                            @error('name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Phone
                            <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="40" autocomplete="tel">
                            @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Email (optional)
                            <input type="email" name="email" value="{{ old('email') }}" autocomplete="email">
                            @error('email') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Message
                            <textarea name="message" required maxlength="1000" style="height: 90px" placeholder="{{ $product->isService() ? 'What you need, and where' : 'How many, and delivery or collection' }}">{{ old('message') }}</textarea>
                            @error('message') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <button type="submit" class="btn btn--gold">Send message</button>
                    </form>
                @endif

                <p class="contact-note">You pay {{ $shop->name }} directly, on terms you agree with them. {{ config('church.short_name') }} lists member businesses but isn't part of the sale.</p>
            </aside>
        </div>

        @if ($more->isNotEmpty())
            <div class="stack" style="gap: 20px; margin-top: 64px">
                <h2 class="h2 h2--sm">More from {{ $shop->name }}</h2>
                <div class="grid-auto" style="--min: 230px; --gap: 22px 18px">
                    @foreach ($more as $item)
                        @include('market.product-card', ['product' => $item->setRelation('shop', $shop)])
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endsection

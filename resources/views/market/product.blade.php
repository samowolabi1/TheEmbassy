@extends('layouts.app', [
    'title' => $product->name,
    'active' => 'market',
    'description' => \Illuminate\Support\Str::limit($product->description, 150),
])

@php($shop = $product->shop)

@section('content')
    <section class="container notices-hero section--last">
        <a href="{{ route('market.shop', $shop) }}" class="back-link"><x-icon name="chevron-left" :size="16" />{{ $shop->name }}</a>

        <div class="notice-form" style="margin-top: 20px">
            <div class="notice-form__main" style="padding: 0; background: none; border: 0; gap: 20px">
                @if ($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="product-hero-photo">
                @else
                    <div role="img" aria-label="No photo" class="product-hero-photo bg-ice"></div>
                @endif
                <div class="stack" style="gap: 12px">
                    <span class="tag tag--wash">{{ $shop->category }}</span>
                    <h1 class="h2">{{ $product->name }}</h1>
                    <span class="product-hero-price">{{ $product->priceLabel() }}</span>
                    <p class="lede" style="margin: 0; white-space: pre-line">{{ $product->description }}</p>
                    <span class="product__seller">Sold by <a href="{{ route('market.shop', $shop) }}" style="font-weight: 800">{{ $shop->name }}</a></span>
                </div>
            </div>

            <aside class="card notice-form__side" id="buy">
                @if ($product->canBuy() && $paymentsEnabled)
                    <form method="POST" action="{{ route('market.buy', $product) }}" class="notice-form__group" data-market-buy data-price="{{ $product->price }}">
                        @csrf
                        <h2 style="margin: 0; font-family: var(--font-display); font-weight: 800; font-size: 22px; color: var(--navy)">Buy now</h2>
                        @error('quantity') <p class="notice-alert" role="alert" style="margin: 0">{{ $message }}</p> @enderror
                        <label class="field">Quantity
                            <select name="quantity">
                                @foreach (range(1, min(config('market.max_quantity'), $product->stock ?? config('market.max_quantity'))) as $n)
                                    <option value="{{ $n }}" @selected((int) old('quantity', 1) === $n)>{{ $n }}</option>
                                @endforeach
                            </select>
                            @if ($product->stock !== null && $product->stock <= 10)
                                <span class="field-hint">Only {{ $product->stock }} left.</span>
                            @endif
                        </label>
                        <label class="field">Your name
                            <input type="text" name="buyer_name" value="{{ old('buyer_name') }}" required maxlength="100" autocomplete="name">
                            @error('buyer_name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Email (for your receipt)
                            <input type="email" name="buyer_email" value="{{ old('buyer_email') }}" required autocomplete="email">
                            @error('buyer_email') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Phone (the seller will call you)
                            <input type="tel" name="buyer_phone" value="{{ old('buyer_phone') }}" required maxlength="40" autocomplete="tel">
                            @error('buyer_phone') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Delivery or collection
                            <textarea name="delivery_note" required maxlength="500" style="height: 90px" placeholder="Your address, or &quot;I'll collect at church on Sunday&quot;">{{ old('delivery_note') }}</textarea>
                            @error('delivery_note') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <button type="submit" class="btn btn--gold">Pay <span data-market-total>{{ $product->priceLabel() }}</span></button>
                        <span class="field-hint" style="text-align: center">Secure payment by Paystack. The seller arranges delivery with you.</span>
                    </form>
                @else
                    <p class="lede" style="margin: 0">
                        @if (! $product->inStock())
                            Sold out for now.
                        @elseif (! $paymentsEnabled)
                            Online payments aren't switched on yet. Contact {{ $shop->owner_name }} on {{ $shop->phone }} to order.
                        @else
                            Online orders aren't open for this shop yet. Contact {{ $shop->owner_name }} on {{ $shop->phone }}.
                        @endif
                    </p>
                @endif
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

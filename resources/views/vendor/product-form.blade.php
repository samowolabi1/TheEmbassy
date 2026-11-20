@extends('layouts.vendor', ['title' => $product->exists ? 'Edit listing' : 'Add a listing', 'active' => 'products'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('vendor.products.index') }}" class="box__link">← Products &amp; services</a>
            <h1>{{ $product->exists ? 'Edit listing' : 'Add a product or service' }}</h1>
        </div>
    </header>

    <section class="box" style="max-width: 760px">
        <form method="POST" action="{{ $product->exists ? route('vendor.products.update', $product) : route('vendor.products.store') }}" enctype="multipart/form-data" class="form">
            @csrf
            @if ($product->exists) @method('PUT') @endif
            <fieldset class="choice-group">
                <legend>What are you listing?</legend>
                <div class="choices">
                    @foreach ([\App\Models\Product::PRODUCT => 'A product', \App\Models\Product::SERVICE => 'A service'] as $value => $label)
                        <label class="choice"><input type="radio" name="type" value="{{ $value }}" @checked(old('type', $product->type ?? 'product') === $value) data-listing-type><span>{{ $label }}</span></label>
                    @endforeach
                </div>
                <span class="field__hint">Services (e.g. photography, catering, tutoring) are booked for a date and have no stock.</span>
                @error('type') <span class="field-error">{{ $message }}</span> @enderror
            </fieldset>
            <label class="field">Name
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required maxlength="100">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">Description
                <textarea name="description" required maxlength="1000">{{ old('description', $product->description) }}</textarea>
                @error('description') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <div class="form-row">
                <label class="field">Price
                    <input type="number" data-money name="price" value="{{ old('price', $product->price ? $product->price / 100 : '') }}" required min="100" step="1">
                    @error('price') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field"><span>Was (optional) <x-tip text="On sale? Put the old, higher price here. Buyers see it crossed out with how much they save." /></span>
                    <input type="number" data-money name="compare_price" value="{{ old('compare_price', $product->compare_price ? $product->compare_price / 100 : '') }}" min="100" step="1" placeholder="Not on sale">
                    @error('compare_price') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field" data-for-type="product">In stock
                    <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" min="0" placeholder="No limit">
                    <span class="field__hint">Leave empty for made-to-order.</span>
                    @error('stock') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field" data-for-type="service">Price is (optional)
                    <input type="text" name="price_unit" value="{{ old('price_unit', $product->price_unit) }}" maxlength="30" placeholder="e.g. per hour, per session" list="price-units">
                    <datalist id="price-units"><option value="per session"><option value="per hour"><option value="per day"><option value="per event"><option value="per person"></datalist>
                    <span class="field__hint">"per hour" lets buyers book several hours. Leave empty for a fixed price.</span>
                    @error('price_unit') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <fieldset class="field" style="border: 0; padding: 0; margin: 0" id="photos">
                <legend style="margin-bottom: 6px">Photos <x-tip text="Up to {{ $maxPhotos }} photos. The cover shows in the market and on your shop; buyers swipe through the rest. Clear, bright photos on a plain background sell best." /></legend>
                @if ($images->isNotEmpty())
                    <div class="photo-grid" style="margin-bottom: 10px">
                        @foreach ($images as $i => $image)
                            <span class="photo-tile" data-photo-kept>
                                <img class="photo-tile__img" src="{{ $image->thumbUrl() }}" alt="Photo {{ $i + 1 }}">
                                @if ($i === 0)<span class="photo-tile__cover">Cover</span>@endif
                                <label><input type="radio" name="cover" value="{{ $image->id }}" @checked($i === 0)>Cover</label>
                                <label><input type="checkbox" name="remove_images[]" value="{{ $image->id }}">Remove</label>
                            </span>
                        @endforeach
                    </div>
                @endif
                @if ($images->count() < $maxPhotos)
                    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" data-photo-input data-max="{{ $maxPhotos }}" style="height: auto; padding: 12px">
                    <span class="field__hint">{{ $images->isEmpty() ? 'Choose one or more photos (hold Ctrl or ⌘ to pick several, or select several on your phone). The first is the cover.' : 'Add more photos: '.($maxPhotos - $images->count()).' more fit.' }} JPG, PNG or WebP, up to 8 MB each; large photos are made smaller for you.</span>
                    <div class="photo-grid" data-photo-new style="margin-top: 8px"></div>
                    <span class="field__hint" data-photo-note style="color: #a6531a" aria-live="polite"></span>
                @else
                    <span class="field__hint">That's the most photos a listing can have. Remove one to add another.</span>
                @endif
                @error('images') <span class="field-error">{{ $message }}</span> @enderror
                @error('images.*') <span class="field-error">{{ $message }}</span> @enderror
            </fieldset>
            @if (auth()->user()->canUseTokens())
            <fieldset class="token-option">
                <legend class="sr-only">{{ TokenName::title() }}</legend>
                <label class="login__remember"><input type="checkbox" name="accepts_tokens" value="1" @checked(old('accepts_tokens', $product->acceptsTokens()))>Members can get this with {{ TokenName::many() }}</label>
                <label class="field">Price in {{ TokenName::many() }}
                    <input type="number" data-money="{{ TokenName::icon() }}" name="token_price" value="{{ old('token_price', $product->token_price) }}" min="1" step="1" placeholder="e.g. {{ \App\Services\Tokens\TokenValue::tokensFor($product->price ?: 100000) }}">
                    <span class="field__hint">Members earn {{ TokenName::many() }} for taking part in church life. When one buys this with {{ TokenName::many() }}, they come to you after you hand it over, and you can use them to pay your subscription (1 {{ TokenName::one() }} = {{ \App\Services\Tokens\TokenValue::rateLabel() }}). It's up to you.</span>
                    @error('token_price') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </fieldset>
            @endif
            <label class="field">Section in your shop
                <select name="shop_collection_id">
                    <option value="">No section</option>
                    @foreach ($collections as $c)
                        <option value="{{ $c->id }}" @selected((int) old('shop_collection_id', $product->shop_collection_id) === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <span class="field__hint">@if ($collections->isEmpty())<a href="{{ route('vendor.storefront.edit') }}#sections">Add sections</a> such as "Cakes" or "Drinks" to group your listings.@else Add or rename sections in <a href="{{ route('vendor.storefront.edit') }}#sections">My online shop</a>.@endif</span>
                @error('shop_collection_id') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="login__remember"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>Feature it at the top of my shop</label>
            <label class="login__remember"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>Show it in the market</label>
            <button type="submit" class="btn btn--gold" style="align-self: flex-start">{{ $product->exists ? 'Save' : 'Add listing' }}</button>
        </form>
    </section>

    @if ($product->exists)
        <section class="box" style="max-width: 760px">
            <h2>Delete this listing</h2>
            <p class="box__note">It's removed from your shop and the market for good, with its photos. Enquiries about it are kept. To take it down for a while, untick "Show it in the market" instead.</p>
            @error('product') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            <form method="POST" action="{{ route('vendor.products.destroy', $product) }}" data-confirm="Delete &quot;{{ $product->name }}&quot; for good?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm">Delete listing</button>
            </form>
        </section>
    @endif
@endsection

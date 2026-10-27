@extends('layouts.vendor', ['title' => $product->exists ? 'Edit product' : 'Add a product', 'active' => 'products'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('vendor.products.index') }}" class="box__link">← Products</a>
            <h1>{{ $product->exists ? 'Edit product' : 'Add a product' }}</h1>
        </div>
    </header>

    <section class="box" style="max-width: 760px">
        <form method="POST" action="{{ $product->exists ? route('vendor.products.update', $product) : route('vendor.products.store') }}" enctype="multipart/form-data" class="form">
            @csrf
            @if ($product->exists) @method('PUT') @endif
            <label class="field">Name
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required maxlength="100">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">Description
                <textarea name="description" required maxlength="1000">{{ old('description', $product->description) }}</textarea>
                @error('description') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <div class="form-row">
                <label class="field">Price (₦)
                    <input type="number" name="price" value="{{ old('price', $product->price ? $product->price / 100 : '') }}" required min="100" step="1">
                    @error('price') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">In stock
                    <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" min="0" placeholder="No limit">
                    <span class="field__hint">Leave empty for services or made-to-order.</span>
                    @error('stock') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <label class="field">Photo
                @if ($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="Current photo" style="width: 160px; aspect-ratio: 1; object-fit: cover; border-radius: 14px">
                @endif
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" style="height: auto; padding: 12px">
                <span class="field__hint">JPG, PNG or WebP, up to 2 MB.{{ $product->imageUrl() ? ' Choosing a new one replaces it.' : '' }}</span>
                @error('image') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="login__remember"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>Show it in the market</label>
            <button type="submit" class="btn btn--gold" style="align-self: flex-start">{{ $product->exists ? 'Save product' : 'Add product' }}</button>
        </form>
    </section>
@endsection

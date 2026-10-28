@extends('layouts.vendor', ['title' => 'Shop details', 'active' => 'shop'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Shop details</h1>
            <span class="page-head__sub">What buyers see on your shop page, and how they reach you</span>
        </div>
    </header>

    <form method="POST" action="{{ route('vendor.shop.update') }}" class="row">
        @csrf
        @method('PUT')
        <section class="box grow form">
            <div class="form-row">
                <label class="field">Shop name
                    <input type="text" name="name" value="{{ old('name', $shop->name) }}" required maxlength="80">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Category
                    <select name="category">
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('category', $shop->category) === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="field">What you sell
                <textarea name="description" required maxlength="600">{{ old('description', $shop->description) }}</textarea>
                @error('description') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">CAC registration number (optional)
                <input type="text" name="cac_number" value="{{ old('cac_number', $shop->cac_number) }}" maxlength="40">
            </label>
        </section>
        <section class="box side-col form">
            <h2>How buyers reach you</h2>
            <label class="field">Your name
                <input type="text" name="owner_name" value="{{ old('owner_name', $shop->owner_name) }}" required maxlength="100">
                @error('owner_name') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">Phone
                <input type="tel" name="phone" value="{{ old('phone', $shop->phone) }}" required maxlength="40">
                @error('phone') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">WhatsApp (if different)
                <input type="tel" name="whatsapp" value="{{ old('whatsapp', $shop->whatsapp) }}" maxlength="40">
            </label>
            <span class="field__hint">Enquiries are emailed to {{ $shop->email }}, your sign-in email.</span>
            <button type="submit" class="btn btn--navy">Save</button>
        </section>
    </form>
@endsection

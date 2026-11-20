@extends('layouts.vendor', ['title' => 'My online shop', 'active' => 'storefront'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>My online shop</h1>
            <span class="page-head__sub">Make your shop page your own: a banner, your logo and colour, what you're about, and sections to arrange your products in. Buyers find it at <a href="{{ route('market.shop', $shop) }}" target="_blank" rel="noopener" style="font-weight: 700">{{ str_replace(['https://', 'http://'], '', route('market.shop', $shop)) }}</a>.</span>
        </div>
        <div class="actions"><a href="{{ route('market.shop', $shop) }}" class="btn btn--gold" target="_blank" rel="noopener">View my shop</a></div>
    </header>

    <form method="POST" action="{{ route('vendor.storefront.update') }}" enctype="multipart/form-data" class="stack" style="gap: 20px">
        @csrf

        <section class="box form">
            <h2>Look</h2>
            <div class="storefront-preview" style="--shop: {{ $shop->colours()[0] }}; --shop-soft: {{ $shop->colours()[1] }}" data-storefront-preview>
                <x-shop-banner :shop="$shop" class="storefront-preview__banner" :preview="true" />
                <div class="storefront-preview__bar">
                    <span class="storefront-preview__logo" data-preview-logo><x-shop-logo :shop="$shop" :size="58" /></span>
                    <span class="stack" style="gap: 2px; min-width: 0">
                        <strong>{{ $shop->name }}</strong>
                        <span class="box__note" style="margin: 0" data-preview-tagline>{{ $shop->tagline ?: $shop->category }}</span>
                    </span>
                </div>
            </div>
            <div class="form-row">
                <label class="field">Banner <x-tip text="A wide picture across the top of your shop: your best products, your kitchen, your studio. It's cropped to fit, so keep the important part in the middle. Best at least 1600 × 600." />
                    <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" data-preview-input="banner" style="height: auto; padding: 12px">
                    <span class="field__hint">JPG, PNG or WebP, up to 8 MB.</span>
                    @if ($shop->bannerUrl())<label class="login__remember"><input type="checkbox" name="remove_banner" value="1">Remove my banner</label>@endif
                </label>
                <label class="field">Logo <x-tip text="Square works best. Without one, your shop shows its initials in your colour." />
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-preview-input="logo" style="height: auto; padding: 12px">
                    <span class="field__hint">Square, up to 4 MB.</span>
                    @if ($shop->logoUrl())<label class="login__remember"><input type="checkbox" name="remove_logo" value="1">Remove my logo</label>@endif
                </label>
            </div>
            <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                <legend style="margin-bottom: 8px">Shop colour</legend>
                <div class="swatches">
                    @foreach ($accents as $key => [$label, $main, $soft])
                        <label class="swatch" style="--swatch: {{ $main }}; --swatch-soft: {{ $soft }}">
                            <input type="radio" name="accent" value="{{ $key }}" @checked(old('accent', $shop->accent ?? 'navy') === $key) data-accent="{{ $main }}|{{ $soft }}">
                            <span class="swatch__dot" aria-hidden="true"></span>{{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <label class="field">Tagline <span class="field__hint" style="display: inline">(one line under your name)</span>
                <input type="text" name="tagline" value="{{ old('tagline', $shop->tagline) }}" maxlength="120" placeholder="e.g. Home-made cakes for every celebration" data-preview-text="tagline" data-fallback="{{ $shop->category }}">
            </label>
            <label class="field">Announcement <x-tip text="A short notice in a bar at the top of your shop: a sale, a new product, holiday hours. Leave empty to hide it." />
                <input type="text" name="announcement" value="{{ old('announcement', $shop->announcement) }}" maxlength="160" placeholder="e.g. 10% off all birthday cakes this December 🎂">
            </label>
        </section>

        <section class="box form">
            <h2>About your shop</h2>
            <label class="field">Your story
                <textarea name="about" rows="5" maxlength="3000" placeholder="How you started, what makes your products special, who you serve…">{{ old('about', $shop->about) }}</textarea>
                <span class="field__hint">Shown in the About section of your shop. Your short description ("{{ \Illuminate\Support\Str::limit($shop->description, 60) }}") is changed in Shop details.</span>
            </label>
            <div class="form-row">
                <label class="field">Opening hours (optional)
                    <input type="text" name="hours" value="{{ old('hours', $shop->hours) }}" maxlength="200" placeholder="e.g. Mon–Sat, 9am–6pm">
                </label>
                <label class="field">Where to find you (optional)
                    <input type="text" name="address" value="{{ old('address', $shop->address) }}" maxlength="255" placeholder="e.g. 12 Allen Avenue, Ikeja">
                    <span class="field__hint">Shown with a map link. Leave empty if you only deliver.</span>
                </label>
            </div>
            <div class="form-row">
                <label class="field">Delivery (optional)
                    <textarea name="delivery_info" rows="3" maxlength="1500" placeholder="e.g. Delivery within Lagos in 1–2 days, from ₦2,000. Pick-up at church on Sundays.">{{ old('delivery_info', $shop->delivery_info) }}</textarea>
                </label>
                <label class="field">Returns and refunds (optional)
                    <textarea name="returns_info" rows="3" maxlength="1500" placeholder="e.g. Exchanges within 7 days with the receipt. Cakes can't be returned.">{{ old('returns_info', $shop->returns_info) }}</textarea>
                </label>
            </div>
        </section>

        <section class="box form">
            <h2>Social media</h2>
            <div class="form-row">
                <label class="field">Instagram <input type="text" name="instagram" value="{{ old('instagram', $shop->instagram) }}" maxlength="100" placeholder="@yourshop"></label>
                <label class="field">TikTok <input type="text" name="tiktok" value="{{ old('tiktok', $shop->tiktok) }}" maxlength="100" placeholder="@yourshop"></label>
            </div>
            <div class="form-row">
                <label class="field">Facebook <input type="text" name="facebook" value="{{ old('facebook', $shop->facebook) }}" maxlength="255" placeholder="yourshop or a link"></label>
                <label class="field">Website <input type="text" name="website" value="{{ old('website', $shop->website) }}" maxlength="255" placeholder="yourshop.com"></label>
            </div>
            @foreach (['instagram', 'tiktok', 'facebook', 'website'] as $f)
                @error($f) <span class="field-error">{{ $message }}</span> @enderror
            @endforeach
        </section>

        <div><button type="submit" class="btn btn--gold">Save my online shop</button></div>
    </form>

    <section class="box form" id="sections" style="margin-top: 20px">
        <h2>Sections <x-tip text="Group your products, e.g. Cakes, Small chops, Drinks. Buyers can tap a section to see just those. Choose each product's section when you add or edit it." /></h2>
        @if ($errors->collections->any())<p class="field-error" role="alert" style="margin: 0">{{ $errors->collections->first() }}</p>@endif
        <form method="POST" action="{{ route('vendor.storefront.collections') }}" class="stack" style="gap: 10px">
            @csrf
            @method('PUT')
            @forelse ($collections as $c)
                <div class="collection-row">
                    <label class="sr-only" for="c-name-{{ $c->id }}">Section name</label>
                    <input id="c-name-{{ $c->id }}" type="text" name="collections[{{ $c->id }}][name]" value="{{ $c->name }}" maxlength="60" class="select-sm" style="flex: 1 1 200px">
                    <label class="collection-row__order">Order <input type="number" name="collections[{{ $c->id }}][position]" value="{{ $c->position }}" min="0" max="999" class="select-sm" style="width: 72px"></label>
                    <span class="box__note" style="margin: 0">{{ $c->products_count }} {{ \Illuminate\Support\Str::plural('item', $c->products_count) }}</span>
                    <label class="login__remember"><input type="checkbox" name="collections[{{ $c->id }}][remove]" value="1">Remove</label>
                </div>
            @empty
                <p class="box__note" style="margin: 0">No sections yet. Everything shows together until you add some.</p>
            @endforelse
            <div class="collection-row">
                <label class="sr-only" for="c-new">New section</label>
                <input id="c-new" type="text" name="new" maxlength="60" placeholder="Add a section, e.g. Small chops" class="select-sm" style="flex: 1 1 200px">
            </div>
            <div><button type="submit" class="btn btn--navy btn--sm">Save sections</button></div>
        </form>
    </section>
@endsection

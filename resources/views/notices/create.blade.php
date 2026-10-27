@extends('layouts.app', [
    'title' => 'Place a notice',
    'description' => 'Advertise your event, business or service to the '.config('church.short_name').' community.',
])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('notices.index') }}" class="back-link"><x-icon name="chevron-left" :size="16" />Notice board</a>
        <span class="eyebrow">ADVERTISE WITH US</span>
        <h1 class="display notices-hero__title">Place a notice.</h1>
        <p class="lede" style="max-width: 640px">Reach our members with your church event, business, job or service. Pay online, we review it within a day, and it runs for the time you choose.</p>
    </section>

    <section class="container section--tight section--last">
        @unless ($paymentsEnabled)
            <p class="notice-alert" role="status">Online payments aren't switched on yet, so notices can't be placed right now. Please check back soon.</p>
        @endunless
        @error('payment')
            <p class="notice-alert" role="alert">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('notices.store') }}" enctype="multipart/form-data" class="notice-form" novalidate>
            @csrf
            <div class="card notice-form__main">
                <fieldset class="notice-form__group">
                    <legend>About you</legend>
                    <div class="pill-choices">
                        @foreach (['business' => 'A business', 'church' => 'A church or ministry'] as $value => $label)
                            <label class="pill-choice"><input type="radio" name="advertiser_type" value="{{ $value }}" @checked(old('advertiser_type', 'business') === $value)><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                    <div class="grid-auto" style="--min: 220px; --gap: 14px">
                        <label class="field">Business or church name
                            <input type="text" name="organisation" value="{{ old('organisation') }}" required maxlength="120" @error('organisation') aria-invalid="true" @enderror>
                            @error('organisation') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Your name
                            <input type="text" name="contact_name" value="{{ old('contact_name') }}" required maxlength="100" autocomplete="name" @error('contact_name') aria-invalid="true" @enderror>
                            @error('contact_name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Email (for your receipt)
                            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" @error('email') aria-invalid="true" @enderror>
                            @error('email') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Phone (optional, shown on the notice)
                            <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel">
                        </label>
                    </div>
                </fieldset>

                <fieldset class="notice-form__group">
                    <legend>Your notice</legend>
                    <label class="field">Category
                        <select name="category" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Headline
                        <input type="text" name="title" value="{{ old('title') }}" required maxlength="100" @error('title') aria-invalid="true" @enderror>
                        @error('title') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Details
                        <textarea name="body" required maxlength="600" @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                        <span class="field-hint">Up to 600 characters. Say what, when, where and the price if there is one.</span>
                        @error('body') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <div class="grid-auto" style="--min: 220px; --gap: 14px">
                        <label class="field">Link (optional)
                            <input type="url" name="url" value="{{ old('url') }}" placeholder="https://" maxlength="255" @error('url') aria-invalid="true" @enderror>
                            @error('url') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Image (optional)
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="file-input" @error('image') aria-invalid="true" @enderror>
                            <span class="field-hint">JPG, PNG or WebP, up to 2 MB.</span>
                            @error('image') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </fieldset>
            </div>

            <aside class="card notice-form__side">
                <fieldset class="notice-form__group">
                    <legend>How long</legend>
                    @foreach ($packages as $key => $package)
                        <label class="package">
                            <input type="radio" name="package" value="{{ $key }}" data-price="{{ $package['price'] }}" @checked(old('package', array_key_first($packages)) === $key)>
                            <span class="package__label">{{ $package['label'] }}</span>
                            <span class="package__price">₦{{ number_format($package['price']) }}</span>
                        </label>
                    @endforeach
                    @error('package') <span class="field-error">{{ $message }}</span> @enderror
                </fieldset>
                <label class="package package--extra">
                    <input type="checkbox" name="featured" value="1" data-price="{{ $featuredPrice }}" @checked(old('featured'))>
                    <span class="package__label">Feature it<span class="field-hint" style="display: block">Shown first, and on the home page.</span></span>
                    <span class="package__price">+₦{{ number_format($featuredPrice) }}</span>
                </label>
                <label class="agree">
                    <input type="checkbox" name="agree" value="1" @checked(old('agree'))>
                    <span>My notice is honest, family-friendly and something I'm allowed to advertise. I understand it's reviewed before it goes live, and a rejected notice is refunded.</span>
                </label>
                @error('agree') <span class="field-error">{{ $message }}</span> @enderror
                <button type="submit" class="btn btn--gold" @disabled(! $paymentsEnabled)>Pay ₦<span data-notice-total>{{ number_format($packages[old('package', array_key_first($packages))]['price'] + (old('featured') ? $featuredPrice : 0)) }}</span></button>
                <span class="field-hint" style="text-align: center">Secure payment by Paystack: card, transfer or USSD.</span>
            </aside>
        </form>
    </section>
@endsection

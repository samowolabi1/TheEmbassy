@extends('layouts.app', [
    'title' => 'Open a shop',
    'active' => 'market',
    'description' => 'Sell to the '.config('church.short_name').' family on '.config('church.brand.market_name').'.',
])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('market.index') }}" class="back-link"><x-icon name="chevron-left" :size="16" />{{ config('church.brand.market_name') }}</a>
        <span class="eyebrow">SELL ON {{ strtoupper(config('church.brand.market_name')) }}</span>
        <h1 class="display notices-hero__title">Open a shop.</h1>
        <p class="lede" style="max-width: 680px">
            Sell your products or services to the church family. Payments go straight to your bank account,
            less a {{ rtrim(rtrim(number_format(\App\Models\Setting::marketCommission(), 2), '0'), '.') }}% commission that supports the church, and Paystack's fee.
        </p>
    </section>

    <section class="container section--tight section--last">
        @if (session('applied'))
            <div class="card notice-status stack" style="gap: 12px; max-width: 760px">
                <span class="eyebrow">APPLICATION SENT</span>
                <h2 class="h2 h2--sm">Thanks! We'll review {{ session('applied') }}.</h2>
                <p class="lede" style="margin: 0">Once it's approved, we'll email you a link to set your password and add your products.</p>
            </div>
        @else
            <form method="POST" action="{{ route('market.apply.store') }}" class="notice-form" novalidate>
                @csrf
                <div class="card notice-form__main">
                    <fieldset class="notice-form__group">
                        <legend>Your shop</legend>
                        <div class="grid-auto" style="--min: 220px; --gap: 14px">
                            <label class="field">Shop name
                                <input type="text" name="name" value="{{ old('name') }}" required maxlength="80" @error('name') aria-invalid="true" @enderror>
                                @error('name') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Category
                                <select name="category" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label class="field">What you sell
                            <textarea name="description" required maxlength="600" @error('description') aria-invalid="true" @enderror>{{ old('description') }}</textarea>
                            @error('description') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">CAC registration number (optional)
                            <input type="text" name="cac_number" value="{{ old('cac_number') }}" maxlength="40">
                        </label>
                    </fieldset>

                    <fieldset class="notice-form__group">
                        <legend>About you</legend>
                        <div class="grid-auto" style="--min: 220px; --gap: 14px">
                            <label class="field">Your name
                                <input type="text" name="owner_name" value="{{ old('owner_name') }}" required maxlength="100" autocomplete="name">
                                @error('owner_name') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Email (your login)
                                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                                @error('email') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Phone
                                <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="40" autocomplete="tel">
                                @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    </fieldset>
                </div>

                <aside class="card notice-form__side">
                    <fieldset class="notice-form__group">
                        <legend>Where we pay you</legend>
                        @if ($banks)
                            <label class="field">Bank
                                <select name="bank_code" required>
                                    <option value="">Choose your bank</option>
                                    @foreach ($banks as $code => $name)
                                        <option value="{{ $code }}" @selected(old('bank_code') === (string) $code)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('bank_code') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        @else
                            <label class="field">Bank
                                <input type="text" name="bank_name" value="{{ old('bank_name') }}" required maxlength="80">
                                @error('bank_name') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        @endif
                        <label class="field">Account number
                            <input type="text" name="account_number" value="{{ old('account_number') }}" required inputmode="numeric" maxlength="10" autocomplete="off">
                            @error('account_number') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </fieldset>
                    <label class="agree">
                        <input type="checkbox" name="agree" value="1" @checked(old('agree'))>
                        <span>I'll only sell honest, family-friendly goods and services, deliver what buyers pay for, and answer buyers quickly. The church may suspend shops that don't.</span>
                    </label>
                    @error('agree') <span class="field-error">{{ $message }}</span> @enderror
                    <button type="submit" class="btn btn--gold">Send application</button>
                </aside>
            </form>
        @endif
    </section>
@endsection

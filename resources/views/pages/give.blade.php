@extends('layouts.app', [
    'title' => 'Give',
    'description' => 'Give tithes and offerings by bank transfer, in naira or other currencies, and donate by transfer or card.',
])

@php
    $naira = fn (int $n) => number_format($n);
    $currencies = $allAccounts->pluck('currency')->unique()->values();
@endphp

@section('content')
    <section class="give-hero bg-navy on-dark">
        <x-crown :width="600" :height="500" jewels class="crown-bg" style="left: -200px; bottom: -220px; opacity: 0.08" />
        <div class="container give-hero__inner">
            <div class="stack give-hero__copy">
                <span class="eyebrow">GIVE</span>
                <h1 class="display give-hero__title">Honour God<br><span class="accent">with your increase.</span></h1>
                <p class="give-hero__text">Tithes and offerings are received by bank transfer, in naira or other currencies. Donations can be made by transfer or online.</p>
                <div class="give-hero__links">
                    <a href="#tithes" class="btn btn--gold">Tithes &amp; offerings</a>
                    <a href="#donate" class="btn btn--outline-light">Make a donation</a>
                </div>
            </div>

            @if ($funds->isNotEmpty())

            <form method="POST" action="{{ route('give.store') }}#amount" class="give-form" id="donate" data-give-form @if ($paymentsEnabled) data-payments-on @endif novalidate>
                @csrf
                <div class="stack" style="gap: 4px">
                    <span class="give-form__title">Donate online</span>
                    <span class="give-note" style="text-align: left">For donations only. Tithes and offerings are by <a href="#tithes" style="color: inherit; text-decoration: underline">bank transfer</a>.</span>
                </div>

                <div class="stack" style="gap: 10px">
                    <label class="give-label" for="amount">AMOUNT</label>
                    <div class="amount">
                        <span class="amount__currency" aria-hidden="true">₦</span>
                        <input id="amount" name="amount" type="text" inputmode="numeric" autocomplete="off" value="{{ old('amount', $naira($defaultAmount)) }}" aria-describedby="amount-help" data-amount>
                    </div>
                    <span id="amount-help" class="sr-only">Amount in naira</span>
                    <div class="presets" role="group" aria-label="Quick amounts">
                        @foreach ($presets as $value)
                            <button type="button" class="preset" aria-pressed="{{ $value === $defaultAmount ? 'true' : 'false' }}" data-preset="{{ $value }}">₦{{ $value / 1000 }}k</button>
                        @endforeach
                    </div>
                </div>

                <fieldset class="stack funds-wrap">
                    <legend class="give-label">DONATE TOWARDS</legend>
                    <div class="funds">
                        @foreach ($funds as $fund)
                            <label class="fund"><input type="radio" name="fund" value="{{ $fund->id }}" @checked((int) old('fund', $funds->first()->id) === $fund->id) @if ($appeal?->is($fund)) data-appeal-fund @endif><span>{{ $fund->name }}</span></label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="field give-email">EMAIL FOR YOUR RECEIPT
                    <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required placeholder="you@example.com" autocomplete="email">
                </label>
                <label class="field give-email">YOUR NAME (OPTIONAL)
                    <input type="text" name="name" value="{{ old('name', $member?->name) }}" maxlength="100" autocomplete="name">
                </label>

                @php($giveError = $errors->first('amount') ?: $errors->first('fund') ?: $errors->first('email'))
                <p class="give-error" role="alert" @unless ($giveError) hidden @endunless data-give-error>{{ $giveError }}</p>
                <button type="submit" class="btn btn--gold give-submit">Donate ₦<span data-amount-label>{{ $naira($defaultAmount) }}</span></button>
                <span class="give-note"><x-icon name="lock" :size="14" /> Card, bank transfer or USSD on the next step, secured by Paystack. Email receipt.</span>
            </form>
            @endif
        </div>
    </section>

    <section id="tithes" class="container section--tight stack" style="gap: 20px" data-filter-scope>
        <div class="stack" style="gap: 10px; max-width: 720px">
            <span class="eyebrow">TITHES &amp; OFFERINGS</span>
            <h2 class="h2">Give by bank transfer.</h2>
            <p class="lede" style="margin: 0">We receive tithes and offerings by bank transfer only. Put <strong>"Tithe"</strong> or <strong>"Offering"</strong> and your name as the transfer description. No data? Use your bank's USSD code with the naira account.</p>
        </div>

        @if ($titheAccounts->pluck('currency')->unique()->count() > 1)
            <div role="tablist" aria-label="Currency" class="filters">
                @foreach ($titheAccounts->pluck('currency')->unique() as $currency)
                    <button type="button" role="tab" class="filter" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-filter="{{ $currency }}">{{ $currency }} · {{ \App\Models\BankAccount::CURRENCIES[$currency][0] ?? $currency }}</button>
                @endforeach
            </div>
        @endif

        <div class="bank-grid">
            @forelse ($titheAccounts as $account)
                @include('pages.partials.bank-account', ['account' => $account, 'hidden' => $titheAccounts->pluck('currency')->unique()->count() > 1 && $account->currency !== $titheAccounts->first()->currency])
            @empty
                <div class="card way">
                    <span class="icon-tile" style="background: var(--wash); color: var(--bronze)"><x-icon name="bank" :size="24" /></span>
                    <span class="way__title">Bank transfer</span>
                    <span class="stack way__bank">
                        <span>{{ config('church.bank.name') }}</span>
                        <span class="way__account">{{ config('church.bank.account_number') }}</span>
                        <span>{{ config('church.bank.account_name') }}</span>
                    </span>
                </div>
            @endforelse
        </div>
    </section>

    <section class="container section--tight stack" style="gap: 20px">
        <div class="stack" style="gap: 10px; max-width: 720px">
            <span class="eyebrow">DONATIONS</span>
            <h2 class="h2">Donate by transfer or online.</h2>
            <p class="lede" style="margin: 0">
                @if ($funds->isNotEmpty())
                    Give to {{ $funds->pluck('name')->join(', ', ' or ') }}
                    {{ $donationAccounts->isEmpty() ? 'by transfer to any account above' : 'by transfer to any account above or below' }}@if ($paymentsEnabled), or <a href="#donate" class="accent-bronze">online by card, bank transfer or USSD</a>@endif.
                @else
                    Donate by transfer to {{ $donationAccounts->isEmpty() ? 'any account above' : 'any account above or below' }}.
                @endif
                Put the project's name as the transfer description.
            </p>
        </div>
        @if ($donationAccounts->isNotEmpty())
            <div class="bank-grid">
                @foreach ($donationAccounts as $account)
                    @include('pages.partials.bank-account', ['account' => $account, 'hidden' => false])
                @endforeach
            </div>
        @endif
    </section>

    @if ($appeal)
        @php($percent = min(100, (int) floor($appealRaised * 100 / max(1, $appeal->target))))
        <section class="container section--tight section--last">
            <div class="building bg-gold">
                <div class="stack" style="gap: 14px">
                    <span class="eyebrow eyebrow--ink">{{ strtoupper($appeal->name) }}</span>
                    <h2 class="h2" style="color: inherit; font-size: clamp(32px, 4vw, 52px)">A new home for the Tribe.</h2>
                    <span class="building__text">{{ $appeal->description ?: 'Every gift brings us closer.' }}</span>
                </div>
                <div class="building__card">
                    <div class="building__amounts">
                        <span class="building__raised">₦{{ number_format($appealRaised / 100) }}</span>
                        <span class="building__target">of ₦{{ number_format($appeal->target / 100) }}</span>
                    </div>
                    <div class="progress" role="progressbar" aria-label="{{ $appeal->name }} progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
                        <div class="progress__bar" style="width: {{ $percent }}%"></div>
                    </div>
                    <span class="building__givers">{{ number_format($appealGivers) }} {{ \Illuminate\Support\Str::plural('giver', $appealGivers) }} so far</span>
                    <a href="#amount" class="btn btn--navy btn--md" style="align-self: flex-start" data-pick-fund>Give to the {{ \Illuminate\Support\Str::lower($appeal->name) }}</a>
                </div>
            </div>
        </section>
    @endif
@endsection

@extends('layouts.app', [
    'title' => 'Give',
    'description' => 'Give tithes, offerings and to the building fund by card, bank transfer or USSD.',
])

@php
    $bank = config('church.bank');
    $naira = fn (int $n) => number_format($n);
@endphp

@section('content')
    <section class="give-hero bg-navy on-dark">
        <x-crown :width="600" :height="500" jewels class="crown-bg" style="left: -200px; bottom: -220px; opacity: 0.08" />
        <div class="container give-hero__inner">
            <div class="stack give-hero__copy">
                <span class="eyebrow">GIVE</span>
                <h1 class="display give-hero__title">Honour God<br><span class="accent">with your increase.</span></h1>
                <p class="give-hero__text">Your giving funds worship, outreach, welfare and the new youth sanctuary. Every gift gets an instant receipt.</p>
                <div class="assurances">
                    <span><x-icon name="lock" :size="18" />Secured by Paystack</span>
                    <span><x-icon name="check" :size="18" />Email receipt</span>
                </div>
            </div>

            <form method="POST" action="{{ route('give.store') }}#amount" class="give-form" data-give-form @if ($paymentsEnabled) data-payments-on @endif novalidate>
                @csrf

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
                    <legend class="give-label">GIVE TOWARDS</legend>
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
                <button type="submit" class="btn btn--gold give-submit">Give ₦<span data-amount-label>{{ $naira($defaultAmount) }}</span></button>
                <span class="give-note">Card, bank transfer or USSD on the next step</span>
            </form>
        </div>
    </section>

    <section class="container section--tight stack" style="gap: 24px">
        <h2 class="h2">Other ways to give</h2>
        <div class="grid-auto" style="--min: 300px; --gap: 16px">
            <div class="card way">
                <span class="icon-tile" style="background: var(--wash); color: var(--bronze)"><x-icon name="bank" :size="24" /></span>
                <span class="way__title">Bank transfer</span>
                <span class="stack way__bank">
                    <span>{{ $bank['name'] }}</span>
                    <span class="way__account">{{ $bank['account_number'] }}</span>
                    <span>{{ $bank['account_name'] }}</span>
                </span>
                <button type="button" class="btn btn--outline btn--sm" style="align-self: flex-start" data-copy="{{ $bank['account_number'] }}" data-copy-label="Copy number">Copy number</button>
            </div>
            <div class="card way">
                <span class="icon-tile" style="background: var(--ice); color: var(--navy)"><x-icon name="phone" :size="24" /></span>
                <span class="way__title">USSD</span>
                <span class="way__text">No data? Dial your bank's USSD code and pay with the church's account number above. Use your name as the description.</span>
            </div>
            <div class="way bg-navy">
                <span class="icon-tile" style="background: var(--gold); color: var(--midnight)"><x-icon name="heart" :size="24" /></span>
                <span class="way__title">In the app</span>
                <span class="way__text" style="color: var(--mist)">Members get a personal account number, giving history and yearly statements in the {{ config('church.brand.app_name') }}.</span>
                <a href="{{ route('home') }}#app" class="btn btn--white btn--sm" style="align-self: flex-start">Get the app</a>
            </div>
        </div>
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

@extends('layouts.app', ['title' => 'Tokens', 'active' => null])

@php($plan = $member->tokenPlan)

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
        <span class="eyebrow">TOKENS</span>
        <h1 class="display notices-hero__title">Your monthly tokens.</h1>
    </section>

    <section class="container section--tight section--last">
        @if ($returned)
            <p class="notice-alert" role="status">
                {{ $returned->status === 'paid' ? 'Thank you! '.$returned->periodLabel().' is paid.' : 'We\'re confirming your payment with Paystack. Refresh in a minute.' }}
            </p>
        @endif
        <div class="account-grid">
            <div class="card">
                <h2>Status</h2>
                @if ($member->status !== 'active' || ! $plan || $plan->isExempt())
                    <p class="lede" style="margin: 0">There are no tokens to pay on your plan.</p>
                @else
                    <dl class="facts">
                        <dt>Plan</dt><dd>{{ $plan->name }} · {{ $plan->amountLabel() }} a month</dd>
                        <dt>Status</dt><dd>{{ $member->tokenStatus() }}</dd>
                        <dt>Next month due</dt><dd>{{ $member->nextTokenMonth()->format('F Y') }}</dd>
                    </dl>
                    <form method="POST" action="{{ route('account.tokens.pay') }}" class="notice-form__group" data-token-form data-amount="{{ $plan->amount }}">
                        @csrf
                        <label class="field">How many months?
                            <select name="months">
                                @foreach (range(1, 12) as $n)
                                    <option value="{{ $n }}" @selected($n === max(1, min(12, $member->tokenMonthsOwed())))>{{ $n }} {{ \Illuminate\Support\Str::plural('month', $n) }} · ₦{{ number_format($plan->amount * $n / 100) }}</option>
                                @endforeach
                            </select>
                            @error('months') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <button type="submit" class="btn btn--gold" @disabled(! $paymentsEnabled)>Pay with Paystack</button>
                        @unless ($paymentsEnabled)
                            <span class="field-hint">Online payments aren't switched on yet. Please pay at the church office.</span>
                        @endunless
                    </form>
                @endif
            </div>
            <div class="card">
                <h2>Paid</h2>
                @if ($history->isEmpty())
                    <p class="lede" style="margin: 0">No tokens paid yet.</p>
                @else
                    <ul class="mini-list">
                        @foreach ($history as $payment)
                            <li><span>{{ $payment->periodLabel() }}</span><strong>₦{{ number_format($payment->amount / 100) }}</strong></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
@endsection

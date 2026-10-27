@extends('layouts.vendor', ['title' => 'Subscription', 'active' => 'subscription'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Subscription</h1>
            <span class="page-head__sub">Your listings show in the market while your subscription is active.</span>
        </div>
    </header>

    @if ($returned)
        <p class="flash" role="status">
            @if ($returned->status === \App\Models\VendorSubscription::ACTIVE)
                Payment received. You're subscribed until {{ $returned->ends_at->format('j F Y') }}.
            @else
                We're confirming your payment with Paystack. Refresh in a minute.
            @endif
        </p>
    @endif

    <div class="stats">
        <div class="stat-tile {{ $subscribed ? 'stat-tile--navy' : '' }}">
            <span class="stat-tile__label">Status</span>
            <span class="stat-tile__num" style="font-size: 26px">{{ $subscribed ? 'Listed' : 'Not listed' }}</span>
            <span class="stat-tile__foot">
                @if ($subscribed)
                    Until {{ $until->format('j M Y') }} ({{ $until->diffForHumans(['parts' => 1]) }})
                @elseif ($until && $until->isFuture())
                    Starts soon, runs until {{ $until->format('j M Y') }}
                @elseif ($until)
                    Ran out {{ $until->format('j M Y') }}
                @else
                    Choose a plan below
                @endif
            </span>
        </div>
    </div>

    <section class="box">
        <div class="box__head">
            <h2>{{ $subscribed ? 'Renew or extend' : 'Choose a plan' }}</h2>
            @if ($subscribed)
                <span class="box__note">A renewal starts when your current time runs out, so you don't lose any days.</span>
            @endif
        </div>
        @if ($plans->isEmpty())
            <p class="box__note" style="margin: 0">Plans aren't open yet. The church office will let you know.</p>
        @else
            <form method="POST" action="{{ route('vendor.subscription.store') }}" class="form">
                @csrf
                <fieldset class="plan-options">
                    <legend class="sr-only">Plan</legend>
                    @foreach ($plans as $plan)
                        <label class="plan-option">
                            <input type="radio" name="plan" value="{{ $plan->id }}" required @checked((int) old('plan', $plans->first()->id) === $plan->id)>
                            <span class="plan-option__name">{{ $plan->name }}</span>
                            <span class="plan-option__price">{{ $plan->priceLabel() }}</span>
                            @if ($plan->months > 1)
                                <span class="plan-option__per">₦{{ number_format($plan->monthlyEquivalent() / 100) }} a month</span>
                            @endif
                        </label>
                    @endforeach
                </fieldset>
                @error('plan') <span class="field-error">{{ $message }}</span> @enderror
                <label class="field" style="max-width: 280px">Coupon code (optional)
                    <input type="text" name="coupon" value="{{ old('coupon') }}" maxlength="40" autocomplete="off" style="text-transform: uppercase">
                    @error('coupon') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div>
                    <button type="submit" class="btn btn--gold">Continue</button>
                </div>
                <span class="field__hint">
                    @if ($paymentsEnabled)
                        You'll pay securely with Paystack. Coupons are taken off before you pay; a 100% coupon needs no payment.
                    @else
                        Online payments aren't switched on yet, so only 100% coupons work for now.
                    @endif
                </span>
            </form>
        @endif
    </section>

    <section class="box box--flush">
        <div class="box__head"><h2>History</h2></div>
        <div class="table-wrap">
            <table class="table" style="min-width: 560px">
                <thead><tr><th>PLAN</th><th>COVERS</th><th>COUPON</th><th class="num">PAID</th></tr></thead>
                <tbody>
                    @forelse ($history as $subscription)
                        <tr>
                            <td style="font-weight: 700">{{ $subscription->plan->name }}</td>
                            <td>{{ $subscription->starts_at->format('j M Y') }} – {{ $subscription->ends_at->format('j M Y') }}</td>
                            <td>{{ $subscription->coupon ? $subscription->coupon->code.' ('.$subscription->coupon->percent_off.'%)' : '—' }}</td>
                            <td class="num" style="font-weight: 800; color: var(--navy)">₦{{ number_format($subscription->amount / 100) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><p class="empty-state">No subscriptions yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

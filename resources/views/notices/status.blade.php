@extends('layouts.app', ['title' => 'Your notice'])

@php
    $status = $notice->displayStatus();
    $messages = [
        'awaiting_payment' => ['Waiting for payment', $payment?->status === \App\Models\Payment::FAILED
            ? "Your payment didn't go through. You can try again below."
            : "We haven't received your payment yet. If you've just paid, give it a minute and refresh this page."],
        'in_review' => ['Paid: in review', "Thanks! We've received your payment and will review your notice within a day. You'll be able to see it on the notice board once it's approved."],
        'live' => ['Live on the notice board', 'Your notice is up until '.$notice->ends_at?->format('l j F').'.'],
        'expired' => ['Finished', 'Your notice ran until '.$notice->ends_at?->format('l j F').'. Place a new one any time.'],
        'rejected' => ['Not approved', "We couldn't approve this notice: ".$notice->rejection_reason.'. If you paid, your money will be refunded.'],
    ];
    [$heading, $text] = $messages[$status];
@endphp

@section('content')
    <section class="container notices-hero section--last" style="max-width: 760px">
        <div class="card notice-status stack" style="gap: 16px">
            <span class="eyebrow">YOUR NOTICE</span>
            <h1 class="h2">{{ $heading }}</h1>
            @if (session('payment_error'))
                <p class="notice-alert" role="alert" style="margin: 0">{{ session('payment_error') }}</p>
            @endif
            <p class="lede" style="margin: 0">{{ $text }}</p>
            <dl class="notice-status__facts">
                <dt>Headline</dt><dd>{{ $notice->title }}</dd>
                <dt>From</dt><dd>{{ $notice->organisation }}</dd>
                <dt>Package</dt><dd>{{ $notice->days }} days{{ $notice->featured ? ', featured' : '' }}</dd>
                <dt>Amount</dt><dd>₦{{ number_format($notice->amount / 100) }}</dd>
            </dl>
            <div class="slide__ctas" style="gap: 10px">
                @if ($status === 'awaiting_payment' && $paymentsEnabled)
                    <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('notices.pay', $notice) }}">
                        @csrf
                        <button type="submit" class="btn btn--gold btn--md">Pay ₦{{ number_format($notice->amount / 100) }}</button>
                    </form>
                @endif
                <a href="{{ route('notices.index') }}" class="btn btn--outline btn--md">See the notice board</a>
            </div>
            <p class="field-hint" style="margin: 0">Keep this page's link to check on your notice later.</p>
        </div>
    </section>
@endsection

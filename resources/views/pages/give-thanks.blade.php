@extends('layouts.app', ['title' => 'Thank you', 'active' => null])

@section('content')
    <section class="container section section--last">
        <div class="card auth" style="text-align: center; align-items: center">
            @if ($gift->status === 'paid')
                <span class="eyebrow">THANK YOU</span>
                <h1 class="auth__title">God bless you, {{ \Illuminate\Support\Str::before($gift->name, ' ') }}.</h1>
                <p class="lede" style="margin: 0">We've received your gift of <strong>₦{{ number_format($gift->amount / 100) }}</strong> to {{ $gift->fund->name }}.@if ($gift->email) A receipt is on its way to {{ $gift->email }}.@endif</p>
                <span class="field-hint">Reference {{ $gift->code }}</span>
            @else
                <span class="eyebrow">ALMOST THERE</span>
                <h1 class="auth__title">We're confirming your gift.</h1>
                <p class="lede" style="margin: 0">Paystack is confirming the payment. Refresh this page in a minute. If you were charged, you'll get a receipt by email.</p>
            @endif
            <a href="{{ route('home') }}" class="btn btn--navy">Back to home</a>
        </div>
    </section>
@endsection

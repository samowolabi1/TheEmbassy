@extends('layouts.app', ['title' => 'Privacy notice', 'active' => null])

@php($church = config('church.name'))

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <span class="eyebrow">PRIVACY</span>
        <h1 class="display notices-hero__title">Privacy notice.</h1>
        <p class="lede" style="max-width: 720px">How {{ $church }} uses the details you give us. This notice follows the Nigeria Data Protection Act 2023. Have it checked by someone qualified before relying on it.</p>
    </section>
    <section class="container section--tight section--last">
        <article class="card legal">
            <h2>Who we are</h2>
            <p>{{ $church }} ("we") runs this website. You can reach us through the <a href="{{ route('contact') }}">contact page</a>@if (config('church.email')) or at {{ config('church.email') }}@endif.</p>

            <h2>What we collect, and why</h2>
            <ul>
                <li><strong>Membership:</strong> your name, contact details, birthday, address, unit, fellowship and church milestones, so we can care for you and organise church life.</li>
                <li><strong>Giving, tokens and tickets:</strong> your name, email and what you paid, to give you receipts and keep the church's accounts. Card details go to Paystack, never to us.</li>
                <li><strong>Prayer requests and messages:</strong> what you send us, so the right people can pray and reply. Private prayer requests are only seen by the pastoral team.</li>
                <li><strong>The market and notice board:</strong> sellers' and advertisers' business and contact details, shown publicly so buyers can reach them.</li>
                <li><strong>The newsletter:</strong> your email address, to send the weekly declaration. Every email lets you unsubscribe.</li>
            </ul>

            <h2>Who we share it with</h2>
            <p>Church staff and leaders who need it for their role. Paystack processes payments. Our email and hosting providers store data for us. We don't sell your details or share them for advertising.</p>

            <h2>How long we keep it</h2>
            <p>Membership details while you're a member and for a reasonable time after. Financial records for as long as the law requires for accounts. You can ask us to delete anything we no longer need.</p>

            <h2>Your rights</h2>
            <p>You can ask to see, correct or delete your details, or object to how we use them, through the <a href="{{ route('contact') }}">contact page</a>. Members can update their own details on <a href="{{ route('account') }}">their account page</a>. You can also complain to the Nigeria Data Protection Commission.</p>

            <h2>Cookies</h2>
            <p>We use only the cookies needed to keep you signed in and protect forms from misuse. There are no advertising or tracking cookies.</p>
        </article>
    </section>
@endsection

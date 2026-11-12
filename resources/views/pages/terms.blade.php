@extends('layouts.app', ['title' => 'Terms of use', 'active' => null])

@php($church = config('church.name'))

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <span class="eyebrow">TERMS</span>
        <h1 class="display notices-hero__title">Terms of use.</h1>
        <p class="lede" style="max-width: 720px">The rules for using this website. Have them checked by someone qualified before relying on them.</p>
    </section>
    <section class="container section--tight section--last">
        <article class="card legal">
            <h2>Using the site</h2>
            <p>This website is run by {{ $church }}. Please use it kindly and lawfully. Don't post anything false, hateful or harmful, and don't try to break or misuse the site.</p>

            <h2>Giving</h2>
            <p>Gifts and tithes are voluntary and go to the church fund you choose. Tithes and offerings are received by bank transfer; donations can also be paid through Paystack. If you gave by mistake, contact us within 14 days and we'll look into it.</p>

            <h2>Tokens</h2>
            <p>Tokens are free rewards for taking part in church life, such as coming to church, joining online and the Bible quiz. They have no cash value, can't be bought, sold or exchanged for money, and can only be spent in the market with sellers who accept them, or by sellers on their shop subscription. The church may change how many tokens activities earn, and may correct or remove tokens given by mistake or gained unfairly.</p>

            <h2>Event tickets</h2>
            <p>Tickets are for the named event and date. Each QR code admits one person once. If we cancel an event, paid tickets are refunded.</p>

            <h2>The notice board</h2>
            <p>Notices are reviewed before they appear. We may decline or remove a notice that doesn't fit our values; if we decline a paid notice before it runs, we refund it.</p>

            <h2>{{ config('church.brand.market_name') }}</h2>
            <p>{{ config('church.brand.market_name') }} lists member businesses. Buyers deal with and pay sellers directly, and the church isn't part of the sale or responsible for goods or services. Sellers pay a subscription to be listed; it isn't refundable once the period has started. We may suspend shops that break the market rules.</p>

            <h2>Changes</h2>
            <p>We may update these terms. The version on this page is the one that applies.</p>
        </article>
    </section>
@endsection

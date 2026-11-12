@extends('layouts.app', ['title' => 'My tokens', 'active' => null])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="container section section--last tokens-page">
        <a href="{{ route('account') }}" class="back"><x-icon name="chevron-left" :size="16" />My account</a>

        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="notice-alert" role="alert" style="margin: 0; background: #fdecea; color: #8a1c12">{{ $errors->first() }}</p>
        @endif

        <div class="tokens-hero bg-navy on-dark">
            <span class="eyebrow">MY TOKENS</span>
            <span class="tokens-hero__balance"><span aria-hidden="true">🪙</span> {{ number_format($balance) }}</span>
            <span class="tokens-hero__text">{{ number_format($earnedThisMonth) }} earned this month. Tokens are free: earn them by taking part, and spend them in the {{ config('church.brand.market_name') }} with sellers who accept them.</span>
            <a href="{{ route('market.index', ['show' => 'tokens']) }}" class="btn btn--gold" style="align-self: flex-start">Shop with tokens</a>
        </div>

        @if ($declaration && ! $declared && $user->member?->status === 'active')
            <form method="POST" action="{{ route('account.declare') }}" class="card declare-card">
                @csrf
                <span class="eyebrow">THIS WEEK'S DECLARATION</span>
                @foreach ($declaration->lines as $line)
                    @if (trim((string) $line) !== '') <p class="declare-card__line">{{ $line }}</p> @endif
                @endforeach
                <button type="submit" class="btn btn--navy" style="align-self: flex-start">I declare it · +{{ (int) config('tokens.rules.declaration') }}</button>
            </form>
        @endif

        <div class="stack" style="gap: 14px">
            <h2 class="h2 h2--sm" style="margin: 0">Ways to earn</h2>
            <div class="earn-grid">
                @foreach ($rules as $rule)
                    <div class="earn-card">
                        <span class="earn-card__amount">+{{ $rule['amount'] }}</span>
                        <span class="earn-card__label">{{ $rule['label'] }}</span>
                        <span class="earn-card__text">{{ $rule['text'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($forTokens->isNotEmpty())
            <div class="stack" style="gap: 14px">
                <div class="section-head" style="align-items: center">
                    <h2 class="h2 h2--sm" style="margin: 0">Get it with tokens</h2>
                    <a href="{{ route('market.index', ['show' => 'tokens']) }}" class="accent-bronze" style="font-weight: 800">See all →</a>
                </div>
                <div class="grid-auto" style="--min: 220px; --gap: 14px">
                    @foreach ($forTokens as $product)
                        <a href="{{ route('market.product', $product) }}" class="card token-product">
                            <span class="token-product__name">{{ $product->name }}</span>
                            <span class="token-product__shop">{{ $product->shop->name }}</span>
                            <span class="token-product__price">🪙 {{ number_format($product->token_price) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($orders->isNotEmpty())
            <div class="card stack" style="gap: 12px; padding: 24px">
                <h2 class="h2 h2--sm" style="margin: 0">Bought with tokens</h2>
                @foreach ($orders as $order)
                    <div class="package-row">
                        <span class="stack" style="gap: 2px">
                            <strong>{{ $order->product_name }}</strong>
                            <span class="field-hint">{{ $order->shop->name }} · 🪙 {{ number_format($order->tokens) }} · {{ $order->statusLabel() }}</span>
                            @if ($order->status === 'pending')
                                <span class="package-ref">Show the seller: <strong>{{ $order->code }}</strong></span>
                            @endif
                        </span>
                        @if ($order->status === 'pending')
                            <form method="POST" action="{{ route('token-orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order? Your tokens come back to you.')">
                                @csrf
                                <button type="submit" class="btn btn--outline btn--sm">Cancel</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card stack" style="gap: 4px; padding: 24px">
            <h2 class="h2 h2--sm" style="margin: 0 0 8px">History</h2>
            @forelse ($history as $t)
                <div class="token-row">
                    <span class="stack" style="gap: 2px; min-width: 0">
                        <span class="token-row__text">{{ $t->description }}</span>
                        <span class="field-hint">{{ $t->created_at->format('j M Y, g:i A') }}</span>
                    </span>
                    <strong @class(['token-row__amount', 'token-row__amount--out' => $t->amount < 0])>{{ $t->amount > 0 ? '+' : '−' }}{{ number_format(abs($t->amount)) }}</strong>
                </div>
            @empty
                <p class="lede" style="margin: 0">Nothing yet. Mark your attendance on Sunday to earn your first tokens.</p>
            @endforelse
            @if ($history->hasPages())
                <nav class="site-pager" aria-label="More history">
                    @if (! $history->onFirstPage()) <a href="{{ $history->previousPageUrl() }}" class="btn btn--outline btn--sm">Newer</a> @endif
                    @if ($history->hasMorePages()) <a href="{{ $history->nextPageUrl() }}" class="btn btn--outline btn--sm">Older</a> @endif
                </nav>
            @endif
        </div>
    </section>
@endsection

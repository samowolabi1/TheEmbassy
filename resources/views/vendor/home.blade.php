@extends('layouts.vendor', ['title' => 'Home', 'active' => 'home'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Hello, {{ \App\Models\Member::firstNameOf($shop->owner_name) }} 👋</h1>
            <span class="page-head__sub">
                @if ($shop->isListed())
                    <span class="pill pill--ok">Live</span> Your shop is in the {{ config('church.brand.market_name') }}@if ($until) until {{ $until->format('j M Y') }}@endif.
                @else
                    <span class="pill pill--warn">Not live yet</span> Only you can see your shop until you subscribe.
                @endif
            </span>
        </div>
        <div class="actions"><a href="{{ route('market.shop', $shop) }}" class="btn btn--outline" target="_blank" rel="noopener">View my shop</a></div>
    </header>

    <section class="quick-actions" aria-label="Quick actions" id="quick-actions">
        <a href="{{ route('vendor.products.create') }}" class="quick-action"><span class="quick-action__icon" aria-hidden="true">📦</span>Add a product</a>
        <a href="{{ route('vendor.products.create', ['type' => 'service']) }}" class="quick-action"><span class="quick-action__icon" aria-hidden="true">🛠️</span>Add a service</a>
        <a href="{{ route('vendor.storefront.edit') }}" class="quick-action"><span class="quick-action__icon" aria-hidden="true">🎨</span>Design my shop</a>
        <a href="{{ route('vendor.share.index') }}" class="quick-action"><span class="quick-action__icon" aria-hidden="true">📣</span>Share my shop</a>
    </section>

    <div class="row" style="align-items: flex-start">
        @if ($done < count($steps))
            <section class="box grow" id="getting-started">
                <h2>Getting started <span class="pill pill--info">{{ $done }} of {{ count($steps) }} done</span></h2>
                <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="{{ count($steps) }}" aria-valuenow="{{ $done }}" aria-label="Getting started"><span style="width: {{ round($done / count($steps) * 100) }}%"></span></div>
                <ol class="checklist">
                    @foreach ($steps as $step)
                        <li @class(['checklist__item', 'is-done' => $step['done']])>
                            <span class="checklist__tick" aria-hidden="true">{{ $step['done'] ? '✓' : '' }}</span>
                            <span class="checklist__text"><strong>{{ $step['title'] }}</strong><span>{{ $step['about'] }}</span></span>
                            @unless ($step['done'])<a href="{{ $step['url'] }}" class="btn btn--ghost btn--xs">{{ $step['action'] }}</a>@endunless
                            @if ($step['done'])<span class="sr-only">Done</span>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @else
            <section class="box grow">
                <h2>Your shop is all set up 🎉</h2>
                <p class="box__note">Keep it fresh: add new listings, put something on sale, feature your best sellers, and share often.</p>
            </section>
        @endif

        <aside class="side-col stack" style="gap: 20px">
            <section class="box" id="share-box">
                <h2>Share your shop</h2>
                <p class="box__note" style="margin: 0">Send your shop to customers and groups. Each link opens your shop with your banner and photos.</p>
                @include('market.share-bar', ['item' => $shop, 'cards' => $cards, 'image' => route('market.shop.image', [$shop, 'square'])])
                <a href="{{ route('vendor.share.index') }}" class="box__link">Pictures, captions and QR codes →</a>
            </section>
            <section class="box">
                <h2>At a glance</h2>
                <dl class="glance">
                    @foreach ($stats as $label => $n)
                        <div><dt>{{ $label }}</dt><dd>{{ number_format($n) }}</dd></div>
                    @endforeach
                </dl>
            </section>
        </aside>
    </div>

    @if ($enquiries->isNotEmpty())
        <section class="box box--flush">
            <div class="box__head"><h2>Latest enquiries</h2><a href="{{ route('vendor.enquiries.index') }}" class="box__link">All enquiries</a></div>
            @foreach ($enquiries as $e)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $e->name }}@if (! $e->read_at) <span class="pill pill--info">New</span>@endif</span>
                        <span class="list-item__meta">{{ $e->product?->name ?? 'Your shop' }} · {{ $e->created_at->diffForHumans() }} · {{ \Illuminate\Support\Str::limit($e->message, 80) }}</span>
                    </span>
                </div>
            @endforeach
        </section>
    @endif
@endsection

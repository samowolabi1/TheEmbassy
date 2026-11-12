@extends('layouts.app', ['title' => 'Tokens', 'active' => null])

@section('content')
    <section class="container section section--last">
        <div class="card auth" style="text-align: center; align-items: center">
            <span class="eyebrow">TOKENS</span>
            <h1 class="auth__title">Tokens are for church members.</h1>
            <p class="lede" style="margin: 0">Members earn tokens by coming to church, joining online and taking part, and spend them in the {{ config('church.brand.market_name') }}. Once your membership is confirmed, your wallet opens here.</p>
            <a href="{{ route('account') }}" class="btn btn--navy">My account</a>
        </div>
    </section>
@endsection

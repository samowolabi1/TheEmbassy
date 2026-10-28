@extends('layouts.app', ['title' => 'Offline', 'active' => null])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="container section section--last">
        <div class="card auth" style="text-align: center; align-items: center">
            <x-crown :width="56" :height="47" />
            <span class="eyebrow">NO CONNECTION</span>
            <h1 class="auth__title">You're offline.</h1>
            <p class="lede" style="margin: 0">Check your data or Wi-Fi and try again. Everything will be here when you're back.</p>
            <button type="button" class="btn btn--navy" onclick="location.reload()">Try again</button>
        </div>
    </section>
@endsection

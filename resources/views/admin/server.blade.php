@extends('layouts.admin', ['title' => 'Server check', 'active' => 'settings'])

@php($counts = $checks->countBy('status'))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.settings.edit') }}" class="box__link">← Settings &amp; roles</a>
            <h1>Server check</h1>
            <span class="page-head__sub">What your hosting needs for everything to work: PHP, folders, the cron job, email and safe settings. Anything marked red needs fixing; amber is worth doing. The same check runs with <code>php artisan app:check</code>.</span>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile"><span class="stat-tile__label">All good</span><span class="stat-tile__num" style="color: var(--ok)">{{ $counts['ok'] ?? 0 }}</span></div>
        <div class="stat-tile"><span class="stat-tile__label">Worth doing</span><span class="stat-tile__num" style="color: var(--warn)">{{ $counts['warn'] ?? 0 }}</span></div>
        <div class="stat-tile"><span class="stat-tile__label">Needs fixing</span><span class="stat-tile__num" style="color: #b4462a">{{ $counts['fail'] ?? 0 }}</span></div>
    </div>

    @foreach ($checks->groupBy('group') as $group => $items)
        <section class="box box--flush">
            <div class="box__head"><h2>{{ $group }}</h2></div>
            @foreach ($items as $c)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $c['label'] }}</span>
                        <span class="list-item__meta">{{ $c['detail'] }}</span>
                        @if ($c['status'] !== 'ok' && $c['fix'])
                            <span class="list-item__meta" style="color: var(--ink)"><strong>How to fix:</strong> {{ $c['fix'] }}</span>
                        @endif
                    </span>
                    <span @class(['pill', 'pill--ok' => $c['status'] === 'ok', 'pill--warn' => $c['status'] !== 'ok'])>{{ ['ok' => 'OK', 'warn' => 'Worth doing', 'fail' => 'Fix this'][$c['status']] }}</span>
                </div>
            @endforeach
        </section>
    @endforeach
@endsection

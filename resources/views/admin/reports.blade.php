@extends('layouts.admin', ['title' => 'Reports', 'active' => 'reports'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Reports</h1>
            <span class="page-head__sub">Download a report for any part of church life as an Excel workbook: a summary sheet with the key figures, then the detail behind them. Only administrators and the pastor in charge can see this page.</span>
        </div>
    </header>

    @unless ($excel)
        <p class="notice-alert" role="alert" style="background: #fdecea; color: #8a1c12">This server can't make Excel files yet: ask your host to turn on PHP's <strong>zip</strong> extension.</p>
    @endunless

    <section class="box">
        <h2>Dates <x-tip text="Most reports cover the dates chosen here (services held, money received, follow-ups raised…). The members and units reports list everyone as they are today, with new members and meetings for these dates." /></h2>
        <form method="GET" action="{{ route('admin.reports.index') }}" class="form" style="flex-direction: row; flex-wrap: wrap; align-items: flex-end; gap: 12px">
            <label class="field">From <input type="date" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
            <label class="field">To <input type="date" name="to" value="{{ $to->toDateString() }}" required></label>
            <button type="submit" class="btn btn--navy">Use these dates</button>
        </form>
        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px">
            @foreach ($presets as $label => [$pFrom, $pTo])
                <a href="{{ route('admin.reports.index', ['from' => $pFrom, 'to' => $pTo]) }}" @class(['btn', 'btn--xs', 'btn--navy' => $from->toDateString() === $pFrom && $to->toDateString() === $pTo, 'btn--ghost' => ! ($from->toDateString() === $pFrom && $to->toDateString() === $pTo)])>{{ $label }}</a>
            @endforeach
        </div>
        @if ($errors->any())<p class="field-error" role="alert">{{ $errors->first() }}</p>@endif
    </section>

    <div class="report-grid">
        @foreach ($reports as $key => $r)
            <article class="box report-card">
                <h2>{{ $r['title'] }}@if ($r['giving'] ?? false) <span class="pill pill--warn">Pastor in charge</span>@endif</h2>
                <p class="box__note" style="margin: 0; flex: 1">{{ $r['about'] }}</p>
                <a href="{{ route('admin.reports.download', ['report' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn--gold btn--sm" style="align-self: flex-start" @unless ($excel) aria-disabled="true" @endunless>
                    <x-icon name="download" :size="16" />Download Excel<span class="sr-only">: {{ $r['title'] }}</span>
                </a>
            </article>
        @endforeach
    </div>
    <p class="box__note">{{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}. Workbooks open in Excel, Google Sheets and Numbers.</p>
@endsection

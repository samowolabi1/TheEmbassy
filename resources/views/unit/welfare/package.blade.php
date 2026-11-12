@extends('layouts.unit', ['title' => $package->title, 'active' => 'packages'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('unit.welfare.packages.index', $unit) }}" class="box__link">← Welfare packages</a>
            <h1>{{ $package->title }}</h1>
            <span class="page-head__sub">
                Applications {{ $package->isOpen() ? 'open until '.$package->closes_at->format('D j M, g:i A') : ($package->opens_at->isFuture() ? 'open '.$package->opens_at->format('D j M') : 'closed') }}
                · {{ $package->placesTaken() }}{{ $package->slots ? ' of '.$package->slots : '' }} approved
            </span>
        </div>
        <div class="actions"><a href="{{ route('unit.welfare.packages.export', [$unit, $package]) }}" class="btn btn--outline">Download CSV</a></div>
    </header>

    @if ($errors->any())
        <p class="flash flash--error" role="alert">{{ $errors->first() }}</p>
    @endif

    <div class="row">
        <div class="grow stack" style="gap: 16px">
            <section class="box form">
                <h2>Hand out a package</h2>
                <form method="POST" action="{{ route('unit.welfare.packages.collect', [$unit, $package]) }}" class="form-row" style="align-items: end">
                    @csrf
                    <label class="field">Reference the member shows
                        <input type="text" name="reference" maxlength="12" required placeholder="PK-XXXXXX" autocapitalize="characters" autocomplete="off">
                    </label>
                    <div class="field"><button type="submit" class="btn btn--navy">Mark collected</button></div>
                </form>
            </section>

            <section class="box box--flush">
                <nav class="tabs" aria-label="Applications" style="padding: 16px 20px 0">
                    <a href="{{ route('unit.welfare.packages.show', [$unit, $package]) }}" class="tab" @if (! $status) aria-current="page" @endif>All <span>{{ $counts->sum() }}</span></a>
                    @foreach (['pending' => 'To review', 'approved' => 'Approved', 'waitlist' => 'Waiting list', 'collected' => 'Collected', 'declined' => 'Declined'] as $key => $label)
                        <a href="{{ route('unit.welfare.packages.show', [$unit, $package, 'status' => $key]) }}" class="tab" @if ($status === $key) aria-current="page" @endif>{{ $label }} <span>{{ $counts[$key] ?? 0 }}</span></a>
                    @endforeach
                </nav>
                <form method="GET" class="box__head" role="search">
                    @if ($status) <input type="hidden" name="status" value="{{ $status }}"> @endif
                    <label class="sr-only" for="app-q">Name or reference</label>
                    <input id="app-q" type="search" name="q" value="{{ $search }}" placeholder="Name or reference" class="search-inline__input"
                        style="flex: 1 1 220px; height: 44px; border-radius: 12px; border: 1px solid var(--admin-line); padding: 0 14px; font-size: 14px; min-width: 0">
                    <button type="submit" class="btn btn--ghost btn--xs" style="height: 44px">Search</button>
                </form>
                @forelse ($applications as $a)
                    <div class="list-item list-item--report">
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $a->member?->name }} <span class="box__note">{{ $a->reference }}</span></span>
                            <span class="list-item__meta">
                                Household of {{ $a->household_size ?? '?' }} · applied {{ $a->created_at->format('j M, g:i A') }}
                                @if ($a->member?->unit) · {{ $a->member->unit->name }} @endif
                                @if ($a->collected_at) · collected {{ $a->collected_at->format('j M, g:i A') }} @endif
                            </span>
                            @if ($a->note) <span class="report-quote">{{ $a->note }}</span> @endif
                            @if ($a->decision_note) <span class="list-item__meta">Note to member: "{{ $a->decision_note }}"</span> @endif
                        </span>
                        <span @class(['pill', 'pill--gold' => $a->status === 'pending', 'pill--live' => in_array($a->status, ['approved', 'collected']), 'pill--scheduled' => $a->status === 'waitlist', 'pill--draft' => in_array($a->status, ['declined', 'withdrawn'])])>{{ ucfirst($a->status) }}</span>
                        @if (in_array($a->status, ['pending', 'waitlist', 'approved', 'declined']))
                            <details class="list-item--edit" style="width: 100%">
                                <summary class="btn btn--ghost btn--xs">Decide</summary>
                                <form method="POST" action="{{ route('unit.welfare.packages.decide', [$unit, $package, $a]) }}" class="form" style="padding-top: 8px">
                                    @csrf
                                    <fieldset class="choice-group">
                                        <legend class="sr-only">Decision</legend>
                                        <div class="choices">
                                            @foreach (['approved' => 'Approve', 'waitlist' => 'Waiting list', 'declined' => 'Decline'] as $value => $label)
                                                <label class="choice"><input type="radio" name="status" value="{{ $value }}" @checked($a->status === $value) required><span>{{ $label }}</span></label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <label class="field">Note to the member (optional) <input type="text" name="decision_note" maxlength="300" value="{{ $a->decision_note }}"></label>
                                    <div><button type="submit" class="btn btn--navy btn--xs">Save and let them know</button></div>
                                </form>
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="empty-state">No applications here.</p>
                @endforelse
                @include('admin.partials.pager', ['paginator' => $applications])
            </section>
        </div>

        <section class="box side-col">
            <h2>Package details</h2>
            <form method="POST" action="{{ route('unit.welfare.packages.update', [$unit, $package]) }}" class="form">
                @csrf
                @method('PUT')
                @include('unit.welfare.package-fields')
                <div><button type="submit" class="btn btn--navy">Save</button></div>
            </form>
        </section>
    </div>
@endsection

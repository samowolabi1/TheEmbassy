@extends('layouts.admin', ['title' => 'Members', 'active' => 'members'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Members</h1>
            <span class="page-head__sub">{{ number_format($totals['members']) }} {{ \Illuminate\Support\Str::plural('member', $totals['members']) }} · {{ $totals['units'] }} {{ \Illuminate\Support\Str::plural('unit', $totals['units']) }} · {{ $totals['fellowships'] }} house {{ \Illuminate\Support\Str::plural('fellowship', $totals['fellowships']) }}</span>
        </div>
        <div class="actions">
            <a href="{{ route('admin.members.export', ['tab' => $tab, ...$filters]) }}" class="btn btn--outline">Export</a>
            <a href="{{ route('admin.members.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />Add member</a>
        </div>
    </header>

    @if ($tab === 'active' && $withoutLogin)
        <section class="box" style="display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center">
            <p class="box__note" style="margin: 0; flex: 1 1 260px"><strong>{{ $withoutLogin }} {{ \Illuminate\Support\Str::plural('member', $withoutLogin) }}</strong> with an email {{ $withoutLogin === 1 ? 'has' : 'have' }} no login yet. Send each a link to choose a password, or open someone's profile to give them a temporary password. <a href="{{ route('admin.members.index', ['login' => 'none']) }}" class="box__link">See who</a></p>
            <form method="POST" action="{{ route('admin.members.login.invite-all') }}" data-confirm="Email {{ $withoutLogin }} {{ \Illuminate\Support\Str::plural('member', $withoutLogin) }} a link to choose a password?">
                @csrf
                <button type="submit" class="btn btn--navy btn--sm">Send {{ $withoutLogin }} {{ \Illuminate\Support\Str::plural('link', $withoutLogin) }}</button>
            </form>
        </section>
    @endif

    <nav class="tabs" aria-label="Member status">
        @foreach ($tabs as $key => $t)
            <a href="{{ route('admin.members.index', ['tab' => $key]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $t['label'] }} <span>{{ number_format($t['count']) }}</span></a>
        @endforeach
    </nav>

    <section class="box box--flush">
        <form method="GET" action="{{ route('admin.members.index') }}" class="box__head filters-bar" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="field filters-bar__search">
                <span class="sr-only">Search members</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by name, phone or email">
            </label>
            <label class="sr-only" for="filter-unit">Unit</label>
            <select id="filter-unit" name="unit" class="select-sm">
                <option value="">All units</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((int) ($filters['unit'] ?? 0) === $unit->id)>{{ $unit->name }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="filter-fellowship">Fellowship</label>
            <select id="filter-fellowship" name="fellowship" class="select-sm">
                <option value="">All fellowships</option>
                @foreach ($fellowships as $fellowship)
                    <option value="{{ $fellowship->id }}" @selected((int) ($filters['fellowship'] ?? 0) === $fellowship->id)>{{ $fellowship->name }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="filter-rank">Role</label>
            <select id="filter-rank" name="rank" class="select-sm">
                <option value="">Everyone</option>
                @foreach (\App\Models\Member::RANKS as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['rank'] ?? '') === $key)>{{ \Illuminate\Support\Str::plural($label) }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="filter-training">Training</label>
            <select id="filter-training" name="training" class="select-sm">
                <option value="">Any training</option>
                <option value="unanswered" @selected(($filters['training'] ?? '') === 'unanswered')>Training not answered</option>
                <option value="missing" @selected(($filters['training'] ?? '') === 'missing')>Classes still to do</option>
            </select>
            <label class="sr-only" for="filter-login">Login</label>
            <select id="filter-login" name="login" class="select-sm">
                <option value="">With or without a login</option>
                <option value="none" @selected(($filters['login'] ?? '') === 'none')>No login yet</option>
                <option value="yes" @selected(($filters['login'] ?? '') === 'yes')>Has a login</option>
            </select>
            <button type="submit" class="btn btn--ghost btn--xs">Filter</button>
        </form>
        <div class="table-wrap">
            <table class="table table--cards" style="min-width: 720px">
                <thead><tr><th>NAME</th><th>UNIT</th><th>{{ mb_strtoupper(TokenName::many()) }}</th><th>LAST ATTENDED</th><th class="num"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($members as $m)
                        <tr>
                            <td>
                                <span style="display: flex; align-items: center; gap: 12px">
                                    <span class="avatar" aria-hidden="true">{{ $m->initials() }}</span>
                                    <span class="stack" style="gap: 2px">
                                        <a href="{{ route('admin.members.show', $m) }}" style="font-weight: 800; color: var(--navy)">{{ $m->name }}</a>
                                        <span style="font-size: 13px; color: var(--slate)">{{ $m->phone ?? $m->email }}</span>
                                    </span>
                                </span>
                            </td>
                            <td>{{ $m->unitNames() ?? '—' }}@if ($m->rank !== 'member') <span @class(['pill', 'pill--live' => $m->rank === 'minister', 'pill--ok' => $m->rank === 'worker'])>{{ $m->rankLabel() }}</span>@endif</td>
                            <td style="font-weight: 700">{{ $m->user ? TokenName::icon().' '.number_format($m->user->tokenBalance()) : '—' }}</td>
                            <td style="color: var(--slate)">{{ $m->last_attended_on?->format('j M') ?? '—' }}</td>
                            <td class="num">
                                @if ($m->status === 'pending')
                                    <form method="POST" action="{{ route('admin.members.approve', $m) }}" style="display: inline">
                                        @csrf
                                        <button type="submit" class="btn btn--navy btn--xs">Approve<span class="sr-only"> {{ $m->name }}</span></button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.members.show', $m) }}" class="btn btn--ghost btn--xs">View<span class="sr-only"> {{ $m->name }}</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><p class="empty-state">{{ $tab === 'pending' ? 'Nobody waiting. New sign-ups from the Join page appear here.' : 'No members match.' }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pager', ['paginator' => $members])
    </section>

    <section class="box">
        <div class="box__head">
            <h2>Import from a spreadsheet <x-tip text="Upload a CSV file whose first row is headings, including a name column (phone, email and the rest are optional). Anyone already on the register with the same email or phone is skipped, so nobody is added twice." /></h2>
            <span class="box__note">A CSV with headings in the first row: <code>first_name</code> and <code>last_name</code> (or one <code>name</code> column), and any of <code>phone</code>, <code>email</code>, <code>gender</code>, <code>birthday</code> (a full date, or just the day and month like 15/11), <code>address</code>, <code>unit</code>, <code>fellowship</code>, <code>joined_on</code>. Unit and fellowship names must match the ones set up already. People already on the register (same email or phone) are skipped.</span>
        </div>
        <form method="POST" action="{{ route('admin.members.import') }}" enctype="multipart/form-data" class="form" style="flex-direction: row; flex-wrap: wrap; align-items: center">
            @csrf
            <label class="field" style="flex: 1 1 260px; min-width: 0"><span class="sr-only">CSV file</span>
                <input type="file" name="file" accept=".csv,text/csv" required>
                @error('file') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <button type="submit" class="btn btn--navy">Import CSV</button>
        </form>
    </section>
@endsection

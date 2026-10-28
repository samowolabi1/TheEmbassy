@extends('layouts.admin', ['title' => 'Dashboard', 'active' => 'dashboard'])

@php($naira = fn (int $kobo) => '₦'.number_format($kobo / 100))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <span class="page-head__kicker">{{ now()->format('l, j F') }}</span>
            <h1>{{ $greeting }}, {{ \Illuminate\Support\Str::before($name, ' ') ?: $name }}</h1>
        </div>
        <div class="actions">
            <a href="{{ route('home') }}" class="btn btn--outline">View website</a>
            @if ($can('finance'))
                <a href="{{ route('admin.finance.index') }}#record-gift" class="btn btn--outline">Record offering</a>
            @endif
            @if ($can('content'))
                <a href="{{ route('admin.content') }}#new-announcement" class="btn btn--gold"><x-icon name="plus" :size="16" />New announcement</a>
            @endif
        </div>
    </header>

    @if ($tiles)
        <div class="stats">
            @foreach ($tiles as $tile)
                <a href="{{ $tile['route'] }}" @class(['stat-tile', 'stat-tile--navy' => $tile['navy'] ?? false])>
                    <span class="stat-tile__label">{{ $tile['label'] }}</span>
                    <span class="stat-tile__num">{{ $tile['num'] }}</span>
                    <span class="stat-tile__foot">{{ $tile['foot'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="row">
        @isset($sundays)
            <section class="box grow">
                <div class="box__head" style="padding: 0">
                    <h2>Sunday attendance</h2>
                    <a href="{{ route('admin.attendance.index') }}" class="box__link">Record a service</a>
                </div>
                @include('admin.partials.attendance-chart', ['sundays' => $sundays])
                @if ($last = $sundays->last())
                    <span class="box__note">{{ number_format($last->total()) }} joined on {{ $last->held_on->format('j M') }}: {{ number_format($last->in_person) }} in person, {{ number_format($last->online) }} online.</span>
                @endif
            </section>
        @endisset

        @if ($todo || isset($declaration))
            <section class="box side-col" style="gap: 6px">
                <h2 style="margin-bottom: 8px">Needs your attention</h2>
                @foreach ($todo as $item)
                    <a href="{{ $item['route'] }}" @class(['todo', 'todo--done' => ! $item['count']])>
                        <span class="todo__count" style="background: var(--ice); color: var(--navy)">{{ $item['count'] }}</span>
                        <span class="todo__text">{{ $item['text'] }}</span>
                        <x-icon name="chevron-right" :size="16" />
                    </a>
                @endforeach
                @isset($declaration)
                    <a href="{{ route('admin.content') }}#declaration" @class(['todo', 'todo--done' => $declaration])>
                        <span class="todo__count" style="background: var(--wash); color: var(--bronze)">@if ($declaration) <x-icon name="check" :size="16" /> @else ! @endif</span>
                        <span class="todo__text">{{ $declaration ? "This week's declaration is set" : "Set this week's declaration" }}</span>
                        <x-icon name="chevron-right" :size="16" />
                    </a>
                @endisset
            </section>
        @endif
    </div>

    @isset($funds)
        <div class="row">
            <section class="box side-col">
                <div class="box__head" style="padding: 0"><h2>Giving by fund</h2><span class="box__note">{{ now()->format('F') }} so far</span></div>
                @php($fundMax = max(1, $funds->max('total')))
                @foreach ($funds as $fund)
                    <div class="budget-line">
                        <span class="budget-line__head"><span>{{ $fund['name'] }}</span><span>{{ $naira($fund['total']) }}</span></span>
                        <div class="meter"><span style="width: {{ round($fund['total'] * 100 / $fundMax) }}%"></span></div>
                    </div>
                @endforeach
                <span class="box__note">Tokens collected: <strong>{{ $naira($tokensThisMonth) }}</strong></span>
            </section>

            <section class="box box--flush grow">
                <div class="box__head"><h2>Latest gifts</h2><a href="{{ route('admin.finance.index') }}" class="box__link">View all</a></div>
                <div class="table-wrap">
                    <table class="table" style="min-width: 520px">
                        <thead><tr><th>FROM</th><th>FOR</th><th>METHOD</th><th class="num">AMOUNT</th><th>WHEN</th></tr></thead>
                        <tbody>
                            @forelse ($latestGifts as $gift)
                                <tr>
                                    <td style="font-weight: 700">{{ $gift->name }}</td>
                                    <td>{{ $gift->fund->name }}</td>
                                    <td>{{ $gift->methodLabel() }}</td>
                                    <td class="num" style="font-weight: 800; color: var(--navy)">{{ $naira($gift->amount) }}</td>
                                    <td style="color: var(--slate); white-space: nowrap">{{ $gift->updated_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><p class="empty-state">No gifts yet.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    @endisset

    <div class="row">
        @isset($events)
            <section class="box side-col">
                <h2>Coming up</h2>
                @forelse ($events as $e)
                    <a href="{{ route('admin.events.show', $e) }}" style="display: flex; align-items: center; gap: 12px; color: var(--ink)">
                        <span style="width: 52px; height: 52px; border-radius: 14px; background: {{ $e->is_featured ? 'var(--gold)' : 'var(--navy)' }}; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0">
                            <span style="font-size: 10px; font-weight: 800; color: {{ $e->is_featured ? 'var(--midnight)' : 'var(--white)' }}">{{ strtoupper($e->starts_at->format('M')) }}</span>
                            <span style="font-family: var(--font-display); font-weight: 800; font-size: 20px; line-height: 1; color: {{ $e->is_featured ? 'var(--midnight)' : 'var(--gold)' }}">{{ $e->starts_at->format('d') }}</span>
                        </span>
                        <span class="stack"><span style="font-weight: 800; font-size: 15px">{{ $e->title }}</span><span class="box__note">{{ $e->starts_at->format('D g:i A') }} · {{ $e->booked_count }} booked</span></span>
                    </a>
                @empty
                    <span class="box__note">No upcoming events.</span>
                @endforelse
                <a href="{{ route('admin.events.create') }}" class="box__link">Add an event</a>
            </section>
        @endisset

        @isset($latestPrayer)
            <section class="box grow">
                <div class="box__head" style="padding: 0">
                    <h2>Prayer requests</h2>
                    <a href="{{ route('admin.prayer-requests.index') }}" class="box__link">Open inbox</a>
                </div>
                @forelse ($latestPrayer as $p)
                    <div class="prayer-snippet">
                        <span class="prayer-snippet__meta">
                            {{ $p->is_private ? 'Private' : ($p->name ?: 'Anonymous') }} · {{ $p->created_at->diffForHumans() }}
                            @unless ($p->read_at) · <span class="text-warn">New</span> @endunless
                        </span>
                        <span class="prayer-snippet__text">{{ \Illuminate\Support\Str::limit($p->request, 140) }}</span>
                    </div>
                @empty
                    <p class="box__note" style="margin: 0">No prayer requests yet. They'll appear here when people use the form on the home page.</p>
                @endforelse
            </section>
        @endisset

        @isset($newMembers)
            <section class="box side-col">
                <div class="box__head" style="padding: 0"><h2>New this week</h2><a href="{{ route('admin.members.index') }}" class="box__link">Members</a></div>
                @forelse ($newMembers as $m)
                    <a href="{{ route('admin.members.show', $m) }}" style="display: flex; align-items: center; gap: 12px; color: var(--ink)">
                        <span class="avatar">{{ $m->initials() }}</span>
                        <span class="stack" style="flex: 1; min-width: 0"><span style="font-weight: 800">{{ $m->name }}</span><span class="box__note">{{ $m->status === 'pending' ? 'Waiting for approval' : ($m->unit ? 'Joined '.$m->unit->name : 'New member') }}</span></span>
                        <span class="pill {{ $m->status === 'pending' ? 'pill--warn' : 'pill--ok' }}">{{ $m->status === 'pending' ? 'Review' : 'Member' }}</span>
                    </a>
                @empty
                    <span class="box__note">Nobody new this week.</span>
                @endforelse
            </section>
        @endisset
    </div>
@endsection

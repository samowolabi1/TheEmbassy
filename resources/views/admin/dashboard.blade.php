@extends('layouts.admin', ['title' => 'Dashboard', 'active' => 'dashboard'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <span class="page-head__kicker">{{ now()->format('l, j F') }}</span>
            <h1>{{ $greeting }}, {{ \Illuminate\Support\Str::before($name, ' ') ?: $name }}</h1>
        </div>
        <div class="actions">
            <a href="{{ route('home') }}" class="btn btn--outline">View website</a>
            <a href="{{ route('admin.content') }}#new-announcement" class="btn btn--gold"><x-icon name="plus" :size="16" />New announcement</a>
        </div>
    </header>

    <div class="stats">
        <a href="{{ route('admin.prayer-requests.index', ['show' => 'unread']) }}" class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Unread prayer requests</span>
            <span class="stat-tile__num">{{ number_format($stats['unreadPrayer']) }}</span>
            <span class="stat-tile__foot">{{ number_format($stats['prayerThisMonth']) }} received this month</span>
        </a>
        <a href="{{ route('admin.subscribers.index') }}" class="stat-tile">
            <span class="stat-tile__label">Newsletter subscribers</span>
            <span class="stat-tile__num">{{ number_format($stats['subscribers']) }}</span>
            <span @class(['stat-tile__foot', 'text-ok' => $stats['subscribersThisMonth'] > 0])>+{{ number_format($stats['subscribersThisMonth']) }} this month</span>
        </a>
        <a href="{{ route('admin.content') }}" class="stat-tile">
            <span class="stat-tile__label">Live announcements</span>
            <span class="stat-tile__num">{{ number_format($stats['liveAnnouncements']) }}</span>
            <span class="stat-tile__foot">{{ number_format($stats['scheduledAnnouncements']) }} scheduled</span>
        </a>
    </div>

    <div class="row">
        <section class="box grow">
            <div class="box__head">
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

        <section class="box side-col" style="gap: 6px">
            <h2 style="margin-bottom: 8px">Needs your attention</h2>
            <a href="{{ route('admin.prayer-requests.index', ['show' => 'unread']) }}" @class(['todo', 'todo--done' => ! $stats['unreadPrayer']])>
                <span class="todo__count" style="background: var(--ice); color: var(--navy)">{{ $stats['unreadPrayer'] }}</span>
                <span class="todo__text">Unread prayer requests</span>
                <x-icon name="chevron-right" :size="16" />
            </a>
            <a href="{{ route('admin.market.index') }}" @class(['todo', 'todo--done' => ! $stats['shopApplications']])>
                <span class="todo__count" style="background: var(--ice); color: var(--navy)">{{ $stats['shopApplications'] }}</span>
                <span class="todo__text">Shop applications to review</span>
                <x-icon name="chevron-right" :size="16" />
            </a>
            <a href="{{ route('admin.notices.index') }}" @class(['todo', 'todo--done' => ! $stats['noticesToReview']])>
                <span class="todo__count" style="background: var(--wash); color: var(--bronze)">{{ $stats['noticesToReview'] }}</span>
                <span class="todo__text">Paid notices to review</span>
                <x-icon name="chevron-right" :size="16" />
            </a>
            <a href="{{ route('admin.content') }}#declaration" @class(['todo', 'todo--done' => $hasDeclarationThisWeek])>
                <span class="todo__count" style="background: var(--wash); color: var(--bronze)">
                    @if ($hasDeclarationThisWeek) <x-icon name="check" :size="16" /> @else ! @endif
                </span>
                <span class="todo__text">{{ $hasDeclarationThisWeek ? "This week's declaration is set" : "Set this week's declaration" }}</span>
                <x-icon name="chevron-right" :size="16" />
            </a>
            <a href="{{ route('admin.content', ['week' => now()->addWeek()->startOfWeek()->toDateString()]) }}#declaration" @class(['todo', 'todo--done' => $hasDeclarationNextWeek])>
                <span class="todo__count" style="background: var(--wash); color: var(--bronze)">
                    @if ($hasDeclarationNextWeek) <x-icon name="check" :size="16" /> @else ! @endif
                </span>
                <span class="todo__text">{{ $hasDeclarationNextWeek ? "Next week's declaration is ready" : "Write next week's declaration" }}</span>
                <x-icon name="chevron-right" :size="16" />
            </a>
        </section>
    </div>

    <div class="row">
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

        <section class="box grow">
            <div class="box__head">
                <h2>New subscribers</h2>
                <a href="{{ route('admin.subscribers.index') }}" class="box__link">All subscribers</a>
            </div>
            @forelse ($latestSubscribers as $s)
                <div style="display: flex; justify-content: space-between; gap: 12px; font-size: 14px">
                    <span style="font-weight: 700; overflow-wrap: anywhere">{{ $s->email }}</span>
                    <span class="box__note" style="white-space: nowrap">{{ $s->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <p class="box__note" style="margin: 0">No subscribers yet. People sign up from the footer of every page.</p>
            @endforelse
        </section>
    </div>
@endsection

@extends('layouts.admin', ['title' => 'Weekly email', 'active' => 'subscribers'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Weekly email</h1>
            <span class="page-head__sub">
                Goes out every Monday morning to {{ number_format($counts['receiving']) }} {{ \Illuminate\Support\Str::plural('person', $counts['receiving']) }}: the declaration, announcements, new messages and articles, and events.
            </span>
        </div>
        <div class="actions">
            <a href="{{ route('admin.subscribers.preview') }}" class="btn btn--outline" target="_blank">Preview this week's</a>
            @if (! $thisWeek)
                <form method="POST" action="{{ route('admin.subscribers.send') }}" data-confirm="Send this week's email to {{ $counts['receiving'] }} {{ \Illuminate\Support\Str::plural('person', $counts['receiving']) }} now? Monday's email won't go out again this week.">
                    @csrf
                    <button type="submit" class="btn btn--gold">Send now</button>
                </form>
            @endif
        </div>
    </header>

    @unless ($enabled)
        <p class="flash flash--error" role="status">The Monday email is switched off in Settings &amp; roles › Weekly email. "Send now" still works.</p>
    @endunless

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">This week</span>
            <span class="stat-tile__num" style="font-size: 22px">
                @if (! $thisWeek) Not sent yet
                @elseif ($thisWeek->status === 'sent') Sent
                @else Sending… @endif
            </span>
            <span class="stat-tile__foot">
                @if ($thisWeek) {{ number_format($thisWeek->sent_count) }} delivered to the mail service @if ($thisWeek->failed_count) · {{ $thisWeek->failed_count }} failed @endif
                @else Goes out Monday at {{ config('church.newsletter.send_at') }} @endif
            </span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Waiting to confirm</span>
            <span class="stat-tile__num">{{ number_format($counts['pending']) }}</span>
            <span class="stat-tile__foot">Signed up but haven't clicked the confirm link</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Unsubscribed</span>
            <span class="stat-tile__num">{{ number_format($counts['unsubscribed']) }}</span>
            <span class="stat-tile__foot">Never emailed again</span>
        </div>
    </div>

    <div class="row">
        <section class="box box--flush grow">
            <nav class="tabs" aria-label="Filter subscribers" style="padding: 16px 20px 0">
                <a href="{{ route('admin.subscribers.index') }}" class="tab" @if ($show === 'receiving') aria-current="page" @endif>Receiving <span>{{ $counts['receiving'] }}</span></a>
                <a href="{{ route('admin.subscribers.index', ['show' => 'pending']) }}" class="tab" @if ($show === 'pending') aria-current="page" @endif>Waiting to confirm <span>{{ $counts['pending'] }}</span></a>
                <a href="{{ route('admin.subscribers.index', ['show' => 'unsubscribed']) }}" class="tab" @if ($show === 'unsubscribed') aria-current="page" @endif>Unsubscribed <span>{{ $counts['unsubscribed'] }}</span></a>
            </nav>
            <form method="GET" action="{{ route('admin.subscribers.index') }}" class="box__head" role="search">
                @if ($show !== 'receiving') <input type="hidden" name="show" value="{{ $show }}"> @endif
                <label class="field" style="flex: 1 1 260px; font-size: 13px">
                    <span class="sr-only">Search by email</span>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search by email"
                        style="height: 44px; border-radius: 12px; border: 1px solid var(--admin-line); background: var(--white); padding: 0 14px; font-size: 14px">
                </label>
                <button type="submit" class="btn btn--ghost btn--xs" style="height: 44px">Search</button>
                <a href="{{ route('admin.subscribers.export') }}" class="btn btn--ghost btn--xs" style="height: 44px">Download CSV</a>
            </form>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>EMAIL</th><th>SIGNED UP</th><th class="num"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($subscribers as $s)
                            <tr>
                                <td style="font-weight: 700; overflow-wrap: anywhere">{{ $s->email }}</td>
                                <td style="color: var(--slate); white-space: nowrap">{{ $s->created_at->format('j M Y') }}</td>
                                <td class="num">
                                    <form method="POST" action="{{ route('admin.subscribers.destroy', $s) }}" data-confirm="Remove {{ $s->email }} from the list?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--xs">Remove<span class="sr-only"> {{ $s->email }}</span></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><p class="empty-state">{{ $search ? 'No subscribers match that search.' : 'Nobody here.' }}</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.partials.pager', ['paginator' => $subscribers])
        </section>

        <section class="box box--flush side-col">
            <div class="box__head"><h2>Past emails</h2></div>
            @forelse ($issues as $issue)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">Week of {{ $issue->week_of->format('j M Y') }}</span>
                        <span class="list-item__meta">{{ number_format($issue->sent_count) }} sent @if ($issue->failed_count) · {{ $issue->failed_count }} failed @endif · {{ $issue->sender ? 'sent by '.$issue->sender->name : 'Monday send' }}</span>
                    </span>
                    <span @class(['pill', 'pill--live' => $issue->status === 'sent', 'pill--scheduled' => $issue->status !== 'sent'])>{{ $issue->status === 'sent' ? 'Sent' : 'Sending' }}</span>
                </div>
            @empty
                <p class="empty-state">No emails sent yet.</p>
            @endforelse
        </section>
    </div>
    <p class="box__note" style="margin: 0">New subscribers confirm by email before they get anything, and every email has an unsubscribe link. Emails go out through the mail service set in <code>MAIL_*</code>.</p>
@endsection

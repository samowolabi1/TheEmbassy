@extends('layouts.admin', ['title' => 'Community', 'active' => 'community'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Community</h1>
            <span class="page-head__sub">The members' chat rooms and Bible quizzes. Reports from members land here.</span>
        </div>
        <div class="actions">
            <a href="{{ route('community.index') }}" class="btn btn--outline" target="_blank">Open the community</a>
            <a href="{{ route('admin.community.quizzes.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />New quiz</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Messages this week</span>
            <span class="stat-tile__num">{{ number_format($stats['messages_week']) }}</span>
            <span class="stat-tile__foot">from {{ $stats['people_week'] }} {{ \Illuminate\Support\Str::plural('person', $stats['people_week']) }}</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Reports to review</span>
            <span class="stat-tile__num">{{ $reports->unique('community_message_id')->count() }}</span>
            <span class="stat-tile__foot">Messages members flagged</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Muted</span>
            <span class="stat-tile__num">{{ $muted->count() }}</span>
            <span class="stat-tile__foot">Can read but not post</span>
        </div>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Reports</h2></div>
        @forelse ($reports->groupBy('community_message_id') as $group)
            @php $report = $group->first(); $message = $report->message; @endphp
            <div class="list-item list-item--report">
                <span class="list-item__main">
                    <span class="list-item__title">{{ $message->user?->communityName() ?? 'Former member' }} in {{ $message->room->name }}</span>
                    <span class="report-quote">
                        @if ($message->isRemoved()) <em>Already removed</em>
                        @else {{ \Illuminate\Support\Str::limit($message->body ?? ($message->poll ? 'Poll: '.$message->poll['question'] : 'Photo'), 300) }}
                        @endif
                    </span>
                    @if ($message->image_path)
                        <a href="{{ $message->imageUrl() }}" target="_blank" rel="noopener"><img src="{{ $message->thumbUrl() }}" alt="Reported photo" class="list-thumb" style="width: 96px; height: 72px"></a>
                    @endif
                    <span class="list-item__meta">
                        {{ $message->created_at->format('j M, g:i A') }} ·
                        reported by {{ $group->map(fn ($r) => $r->reporter?->communityName())->filter()->join(', ') }}:
                        {{ $group->pluck('reason')->map(fn ($r) => '"'.$r.'"')->join('; ') }}
                    </span>
                    <a href="{{ route('community.room', $message->room).'?before='.($message->id + 6).'#m'.$message->id }}" class="box__link" target="_blank">See it in the chat</a>
                </span>
                <span class="list-item__actions">
                    <form method="POST" action="{{ route('admin.community.reports.resolve', $report) }}">
                        @csrf
                        <input type="hidden" name="outcome" value="dismissed">
                        <button type="submit" class="btn btn--ghost btn--xs">Keep it</button>
                    </form>
                    <form method="POST" action="{{ route('admin.community.reports.resolve', $report) }}" data-confirm="Remove this message?">
                        @csrf
                        <input type="hidden" name="outcome" value="removed">
                        <button type="submit" class="btn btn--danger btn--xs">Remove</button>
                    </form>
                    @if ($message->user && ! $message->user->moderatesCommunity())
                        <details class="list-item--edit">
                            <summary class="btn btn--danger btn--xs">Remove &amp; mute</summary>
                            <form method="POST" action="{{ route('admin.community.reports.resolve', $report) }}" class="form" style="padding-top: 8px">
                                @csrf
                                <input type="hidden" name="outcome" value="muted">
                                <label class="field">Mute for
                                    <select name="days">
                                        <option value="1">1 day</option>
                                        <option value="3">3 days</option>
                                        <option value="7" selected>1 week</option>
                                        <option value="30">30 days</option>
                                    </select>
                                </label>
                                <label class="field">Reason they'll see <input type="text" name="reason" maxlength="200" placeholder="e.g. Insulting another member"></label>
                                <button type="submit" class="btn btn--danger btn--xs">Remove and mute</button>
                            </form>
                        </details>
                    @endif
                </span>
            </div>
        @empty
            <p class="empty-state">No reports. All quiet. 🙌</p>
        @endforelse
    </section>

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Rooms</h2><span class="box__note">Rooms tied to a unit are only for its members.</span></div>
            @foreach ($rooms as $r)
                <details class="list-item list-item--edit" @if ($errors->getBag('room'.$r->id)->any()) open @endif>
                    <summary>
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $r->emoji }} {{ $r->name }}
                                @if ($r->unit) <span class="pill pill--info">{{ $r->unit->name }} only</span> @endif
                                @if ($r->is_locked) <span class="pill pill--draft">Announcements only</span> @endif
                            </span>
                            <span class="list-item__meta">{{ number_format($r->messages_count) }} {{ \Illuminate\Support\Str::plural('message', $r->messages_count) }} · {{ $r->description }}</span>
                        </span>
                        <span class="btn btn--ghost btn--xs">Edit</span>
                    </summary>
                    <form method="POST" action="{{ route('admin.community.rooms.update', $r->id) }}" class="form">
                        @csrf
                        @method('PUT')
                        @include('admin.community.room-fields')
                        @error('name', 'room'.$r->id) <span class="field-error">{{ $message }}</span> @enderror
                        <div class="list-item__actions">
                            <button type="submit" class="btn btn--navy btn--xs">Save</button>
                            <button type="submit" form="delete-room-{{ $r->id }}" class="btn btn--danger btn--xs">Delete room</button>
                        </div>
                    </form>
                    <form id="delete-room-{{ $r->id }}" method="POST" action="{{ route('admin.community.rooms.destroy', $r->id) }}" data-confirm="Delete {{ $r->name }} and all its messages for good?">
                        @csrf
                        @method('DELETE')
                    </form>
                </details>
            @endforeach
            <details class="list-item list-item--edit" @if ($errors->room->any()) open @endif>
                <summary><span class="list-item__main"><span class="list-item__title">+ New room</span></span></summary>
                <form method="POST" action="{{ route('admin.community.rooms.store') }}" class="form">
                    @csrf
                    @include('admin.community.room-fields', ['r' => new \App\Models\CommunityRoom(['position' => $rooms->max('position') + 1])])
                    @error('name', 'room') <span class="field-error">{{ $message }}</span> @enderror
                    <div><button type="submit" class="btn btn--navy btn--xs">Add room</button></div>
                </form>
            </details>
        </section>

        <div class="side-col stack" style="gap: 16px">
            <section class="box box--flush">
                <div class="box__head"><h2>Bible quizzes</h2></div>
                @forelse ($quizzes as $quiz)
                    <div class="list-item">
                        <span class="list-item__main">
                            <a href="{{ route('admin.community.quizzes.edit', $quiz) }}" class="list-item__title">{{ $quiz->title }}</a>
                            <span class="list-item__meta">{{ $quiz->questions_count }} questions · {{ $quiz->attempts_count }} played · {{ $quiz->opens_at->format('j M') }}–{{ $quiz->closes_at->format('j M') }}</span>
                        </span>
                        @if (! $quiz->is_published) <span class="pill pill--draft">Draft</span>
                        @elseif ($quiz->isOpen()) <span class="pill pill--live">Open</span>
                        @elseif ($quiz->opens_at->isFuture()) <span class="pill pill--scheduled">Scheduled</span>
                        @else <span class="pill">Closed</span> @endif
                    </div>
                @empty
                    <p class="empty-state">No quizzes yet.</p>
                @endforelse
            </section>

            <section class="box box--flush">
                <div class="box__head"><h2>Muted members</h2></div>
                @forelse ($muted as $user)
                    <div class="list-item">
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $user->communityName() }}</span>
                            <span class="list-item__meta">Until {{ $user->community_muted_until->format('j M, g:i A') }}@if ($user->community_mute_reason) · {{ $user->community_mute_reason }}@endif</span>
                        </span>
                        <form method="POST" action="{{ route('admin.community.unmute', $user) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn--ghost btn--xs">Unmute</button>
                        </form>
                    </div>
                @empty
                    <p class="empty-state">Nobody is muted.</p>
                @endforelse
            </section>
        </div>
    </div>
@endsection

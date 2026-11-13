@extends('layouts.admin', ['title' => 'Prayer requests', 'active' => 'prayer'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Prayer requests</h1>
            <span class="page-head__sub">From the form on the home page, and from members' accounts. Private requests are for the pastoral team only. Reply to members to encourage them: they get it by email and on their phone.</span>
        </div>
    </header>

    <nav class="tabs" aria-label="Filter prayer requests">
        @foreach (['all' => 'All', 'unread' => 'Unread', 'private' => 'Private', 'members' => 'From members', 'wall' => 'On the prayer wall'] as $key => $label)
            <a href="{{ route('admin.prayer-requests.index', $key === 'all' ? [] : ['show' => $key]) }}" class="tab" @if ($filter === $key) aria-current="page" @endif>
                {{ $label }} <span>{{ number_format($counts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($requests as $p)
            <article @class(['prayer-card', 'prayer-card--unread' => ! $p->read_at])>
                <div class="prayer-card__head">
                    <span class="prayer-card__who">
                        @if ($p->member && auth()->user()->canManage('members'))
                            <a href="{{ route('admin.members.show', $p->member) }}">{{ $p->member->name }}</a>
                        @elseif ($p->member)
                            {{ $p->member->name }}
                        @else
                            {{ $p->name ?: 'Anonymous' }}
                        @endif
                    </span>
                    @if ($p->categoryLabel())
                        <span class="pill pill--info">{{ $p->categoryLabel() }}</span>
                    @endif
                    @if ($p->is_private)
                        <span class="pill pill--warn">Private</span>
                    @endif
                    @if ($p->on_wall)
                        <span class="pill pill--draft">Prayer wall{{ $p->anonymous ? ', no name' : '' }}{{ $p->prayed_count ? ' · 🙏 '.$p->prayed_count : '' }}</span>
                    @endif
                    @if ($p->answered_at)
                        <span class="pill pill--ok">Answered 🎉</span>
                    @endif
                    @unless ($p->read_at)
                        <span class="pill pill--gold">New</span>
                    @endunless
                    <time class="prayer-card__when" datetime="{{ $p->created_at->toIso8601String() }}" title="{{ $p->created_at->format('D j M Y, g:i A') }}">{{ $p->created_at->diffForHumans() }}</time>
                </div>
                <p class="prayer-card__text">{{ $p->request }}</p>
                @if ($p->answer_note)
                    <p class="report-quote" style="margin: 0"><strong>What God did:</strong> {{ $p->answer_note }}</p>
                @endif
                @if ($p->contact)
                    <span class="prayer-card__contact"><strong>Follow up:</strong> {{ $p->contact }}</span>
                @endif
                @foreach ($p->replies as $r)
                    <p class="box__note" style="margin: 0; white-space: pre-line"><strong>{{ $r->author?->communityName() ?? 'Prayer team' }}, {{ $r->created_at->format('j M') }}:</strong> {{ $r->body }}</p>
                @endforeach
                @if ($p->member)
                    <form method="POST" action="{{ route('admin.prayer-requests.reply', $p) }}" style="display: flex; gap: 8px; flex-wrap: wrap">
                        @csrf
                        <label class="sr-only" for="reply-{{ $p->id }}">Reply to {{ $p->member->name }}</label>
                        <input id="reply-{{ $p->id }}" type="text" name="body" required maxlength="2000" class="select-sm" style="flex: 1 1 260px" placeholder="A word of encouragement or a scripture for {{ \App\Models\Member::firstNameOf($p->member->name) }}">
                        <button type="submit" class="btn btn--navy btn--xs">Send</button>
                    </form>
                @endif
                <div class="list-item__actions">
                    <form method="POST" action="{{ route('admin.prayer-requests.update', $p) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="read" value="{{ $p->read_at ? 0 : 1 }}">
                        <button type="submit" class="btn {{ $p->read_at ? 'btn--ghost' : 'btn--navy' }} btn--xs">{{ $p->read_at ? 'Mark as unread' : 'Mark as prayed for' }}</button>
                    </form>
                    @if ($p->on_wall)
                        <form method="POST" action="{{ route('admin.prayer-requests.off-wall', $p) }}" data-confirm="Take this off the prayer wall?">
                            @csrf
                            <button type="submit" class="btn btn--ghost btn--xs">Take off the wall</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.prayer-requests.destroy', $p) }}" data-confirm="Delete this prayer request for good?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--xs">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="empty-state">{{ $filter === 'all' ? 'No prayer requests yet.' : 'Nothing here.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $requests])
    </section>
@endsection

{{-- The list of rooms, with unread counts. --}}
<nav class="rooms" aria-label="Rooms">
    @foreach ($rooms as $r)
        <a href="{{ route('community.room', $r) }}" class="rooms__item" @if (isset($room) && $room->id === $r->id) aria-current="page" @endif>
            <span class="rooms__emoji" aria-hidden="true">{{ $r->emoji ?: '💬' }}</span>
            <span class="rooms__text">
                <span class="rooms__name">{{ $r->name }}@if ($r->is_locked) <span class="sr-only">(announcements only)</span><span aria-hidden="true"> 🔒</span>@endif</span>
                <span class="rooms__last">
                    @if ($r->latest)
                        {{ $r->latest->user?->communityName() }}: {{ \Illuminate\Support\Str::limit($r->latest->body ?? ($r->latest->poll ? '📊 '.$r->latest->poll['question'] : '📷 Photo'), 48) }}
                    @else
                        {{ $r->description ?: 'No messages yet' }}
                    @endif
                </span>
            </span>
            @if ($r->unread)
                <span class="rooms__unread"><span class="sr-only">unread: </span>{{ $r->unread > 99 ? '99+' : $r->unread }}</span>
            @endif
        </a>
    @endforeach
</nav>

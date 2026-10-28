@extends('layouts.admin', ['title' => 'First-timers & follow-up', 'active' => 'firsttimers'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>First-timers &amp; follow-up</h1>
            <span class="page-head__sub">{{ $thisMonth }} this month · visitors can fill in a card at <a href="{{ route('welcome') }}">{{ route('welcome') }}</a> (put it on a QR code at the door)</span>
        </div>
        <div class="actions">
            <a href="{{ route('admin.first-timers.export') }}" class="btn btn--outline">Export</a>
            <a href="#new-card" class="btn btn--gold"><x-icon name="plus" :size="16" />Add a card</a>
        </div>
    </header>

    <nav class="tabs" aria-label="Follow-up">
        <a href="{{ route('admin.first-timers.index') }}" class="tab" @if ($tab === 'todo') aria-current="page" @endif>To call <span>{{ $counts['todo'] }}</span></a>
        <a href="{{ route('admin.first-timers.index', ['tab' => 'done']) }}" class="tab" @if ($tab === 'done') aria-current="page" @endif>Followed up <span>{{ $counts['done'] }}</span></a>
    </nav>

    <section class="box box--flush">
        @forelse ($firstTimers as $f)
            <article class="prayer-card">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $f->name }}</span>
                    @if ($f->wants_to_join)<span class="pill pill--ok">Wants to join</span>@endif
                    @if ($f->invited_by)<span class="pill pill--info">Invited by {{ $f->invited_by }}</span>@endif
                    <span class="prayer-card__when">Visited {{ $f->visited_on->format('j M') }}</span>
                </div>
                @if ($f->notes)<p class="prayer-card__text">{{ $f->notes }}</p>@endif
                <span class="prayer-card__contact">{{ $f->phone }}@if ($f->email) · {{ $f->email }}@endif</span>
                @if ($f->followed_up_at)
                    <span class="prayer-card__contact"><strong>Called {{ $f->followed_up_at->format('j M') }}@if ($f->follower) by {{ $f->follower->name }}@endif:</strong> {{ $f->follow_up_note ?? 'No note' }}</span>
                @endif
                <div class="list-item__actions" style="flex-wrap: wrap">
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $f->phone) }}" class="btn btn--navy btn--xs">Call</a>
                    @unless ($f->followed_up_at)
                        <form method="POST" action="{{ route('admin.first-timers.follow-up', $f) }}" style="display: flex; gap: 8px; flex-wrap: wrap">
                            @csrf
                            <label class="sr-only" for="note-{{ $f->id }}">How it went</label>
                            <input id="note-{{ $f->id }}" type="text" name="note" maxlength="255" placeholder="How it went" class="select-sm" style="min-width: 200px">
                            <button type="submit" class="btn btn--ghost btn--xs">Mark as called</button>
                        </form>
                    @endunless
                    @if ($f->member)
                        <a href="{{ route('admin.members.show', $f->member) }}" class="btn btn--ghost btn--xs">On the register</a>
                    @elseif (auth()->user()->canManage('members'))
                        <form method="POST" action="{{ route('admin.first-timers.register', $f) }}">
                            @csrf
                            <button type="submit" class="btn btn--ghost btn--xs">Add to register</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.first-timers.destroy', $f) }}" data-confirm="Delete {{ $f->name }}'s card?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--xs">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="empty-state">{{ $tab === 'todo' ? 'Everyone has been called. Well done!' : 'Nobody followed up yet.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $firstTimers])
    </section>

    <section class="box" id="new-card">
        <h2>Add a first-timer card</h2>
        <form method="POST" action="{{ route('admin.first-timers.store') }}" class="form">
            @csrf
            <div class="form-row">
                <label class="field">Name <input type="text" name="name" value="{{ old('name') }}" required maxlength="100">@error('name') <span class="field-error">{{ $message }}</span> @enderror</label>
                <label class="field">Phone <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="40">@error('phone') <span class="field-error">{{ $message }}</span> @enderror</label>
                <label class="field">Email (optional) <input type="email" name="email" value="{{ old('email') }}"></label>
            </div>
            <div class="form-row">
                <label class="field">Invited by (optional) <input type="text" name="invited_by" value="{{ old('invited_by') }}" maxlength="100"></label>
                <label class="field">Date of visit <input type="date" name="visited_on" value="{{ old('visited_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
            </div>
            <label class="field">Notes <input type="text" name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="e.g. Student at UNILAG, interested in the choir"></label>
            <label class="login__remember"><input type="checkbox" name="wants_to_join" value="1" @checked(old('wants_to_join'))>Would like to become a member</label>
            <div><button type="submit" class="btn btn--navy">Add card</button></div>
        </form>
    </section>
@endsection

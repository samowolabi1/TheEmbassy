@extends('layouts.unit', ['title' => $r->title, 'active' => 'requests'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('unit.requests.index', $unit) }}" class="box__link">← Requests</a>
            <h1>{{ $r->title }}</h1>
            <span class="page-head__sub">{{ $r->typeLabel() }} · {{ $r->reference }} · from {{ $r->requester?->member?->name ?? $r->requester?->name }} ({{ $r->fromLabel() }}) · asked {{ $r->created_at->format('j M') }}</span>
        </div>
        <div class="actions" style="align-items: center">
            <span class="pill {{ match ($r->status) { 'new' => 'pill--gold', 'in_progress' => 'pill--info', 'delivered', 'completed' => 'pill--ok', default => 'pill--draft' } }}">{{ \App\Models\MediaRequest::STATUSES[$r->status] }}</span>
            @if ($r->isOpen() && ! $r->assigned_member_id && auth()->user()->member)
                <form method="POST" action="{{ route('unit.requests.assign', [$unit, $r]) }}">
                    @csrf
                    <button type="submit" class="btn btn--gold">Take it</button>
                </form>
            @endif
            @if ($r->status === 'delivered' && $canLead)
                <form method="POST" action="{{ route('unit.requests.close', [$unit, $r]) }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost">Close it</button>
                </form>
            @endif
        </div>
    </header>

    @if ($errors->any())
        <p class="flash flash--error" role="alert">{{ $errors->first() }}</p>
    @endif

    <div class="row">
        <section class="box grow">
            <dl class="detail-list">
                <dt>Needed by</dt><dd>{{ $r->needed_by->format('l j F Y') }}@if ($r->isOverdue()) <span class="pill pill--warn">Overdue</span>@elseif ($r->isUrgent()) <span class="pill pill--gold">Soon</span>@endif</dd>
                @if ($r->event_on)<dt>Event</dt><dd>{{ $r->event_on->format('l j F Y') }}</dd>@endif
                <dt>Working on it</dt><dd>{{ $r->assignee?->name ?? 'Nobody yet' }}</dd>
                <dt>Contact</dt><dd>{{ $r->requester?->email }}{{ $r->requester?->member?->phone ? ' · '.$r->requester->member->phone : '' }}</dd>
                @if ($r->decline_reason)<dt>Declined</dt><dd>{{ $r->decline_reason }}</dd>@endif
            </dl>
            <p style="margin: 0; white-space: pre-line">{{ $r->details }}</p>
            @php($attached = $r->files->where('purpose', 'reference'))
            @if ($attached->isNotEmpty())
                <h2 style="font-size: 15px; margin: 6px 0 0">Sent with the request</h2>
                @include('unit.media.request-files', ['files' => $attached])
            @endif
        </section>

        @if ($r->isOpen() && $canLead)
            <section class="box" style="flex: 0 1 320px">
                <h2>Assign</h2>
                <form method="POST" action="{{ route('unit.requests.assign', [$unit, $r]) }}" class="form">
                    @csrf
                    <label class="field">Team member
                        <select name="member_id" required>
                            @foreach ($team as $m)
                                <option value="{{ $m->id }}" @selected($r->assigned_member_id === $m->id)>{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div><button type="submit" class="btn btn--navy">{{ $r->assigned_member_id ? 'Reassign' : 'Assign' }}</button></div>
                </form>
                <details>
                    <summary class="box__link" style="cursor: pointer">Decline it</summary>
                    <form method="POST" action="{{ route('unit.requests.decline', [$unit, $r]) }}" class="form" style="margin-top: 10px">
                        @csrf
                        <label class="field">Why (they'll see this)
                            <textarea name="reason" required maxlength="300" rows="3" placeholder="e.g. Not enough notice for a video; we can do a graphic instead.">{{ old('reason') }}</textarea>
                        </label>
                        <div><button type="submit" class="btn btn--danger btn--xs">Decline</button></div>
                    </form>
                </details>
            </section>
        @endif
    </div>

    @php($delivered = $r->files->where('purpose', 'deliverable'))
    @if ($delivered->isNotEmpty())
        <section class="box">
            <h2>Delivered</h2>
            @include('unit.media.request-files', ['files' => $delivered])
            <p class="box__note" style="margin: 0">These are in the file library under "From requests". Put one in a folder to keep it with the rest of the library.</p>
        </section>
    @endif

    @if ($canDeliver)
        <section class="box">
            <h2>{{ $r->status === 'in_progress' && $delivered->isNotEmpty() ? 'Deliver the changes' : 'Deliver' }}</h2>
            <form method="POST" action="{{ route('unit.requests.deliver', [$unit, $r]) }}" enctype="multipart/form-data" class="form" data-photo-upload data-noun="file" data-processing="Saving…">
                @csrf
                <label class="dropzone" data-dropzone>
                    <x-icon name="image" :size="28" />
                    <span class="dropzone__title">Choose the finished files or drop them here</span>
                    <span class="field__hint">Up to 20 at a time, {{ config('church.media.max_upload_mb', 500) }} MB each.</span>
                    <input type="file" name="files[]" multiple class="sr-only" data-photo-input>
                    <span class="dropzone__count" data-photo-count aria-live="polite"></span>
                </label>
                <progress max="100" value="0" hidden data-photo-progress></progress>
                <label class="field">Links (one per line, optional)
                    <textarea name="links" rows="2" maxlength="2000" placeholder="https://drive.google.com/…">{{ old('links') }}</textarea>
                </label>
                <label class="field">Note to {{ \Illuminate\Support\Str::before($r->requester?->member?->name ?? $r->requester?->name ?? 'them', ' ') }} (optional)
                    <textarea name="note" rows="2" maxlength="3000">{{ old('note') }}</textarea>
                </label>
                <div><button type="submit" class="btn btn--gold">Deliver and let them know</button></div>
            </form>
        </section>
    @endif

    <section class="box">
        <h2>Messages</h2>
        @forelse ($comments as $c)
            <article class="prayer-card" @if ($c->is_internal) style="background: var(--wash, #f6f1e6)" @endif>
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $c->author?->member?->name ?? $c->author?->name ?? 'Someone' }}{{ $c->user_id === $r->requested_by ? ' (asked for this)' : '' }}</span>
                    <span class="prayer-card__when">{{ $c->is_internal ? 'Team note · ' : '' }}{{ $c->created_at->format('j M, g:i A') }}</span>
                </div>
                <p class="prayer-card__text" style="white-space: pre-line">{{ $c->body }}</p>
            </article>
        @empty
            <p class="empty-state">No messages yet.</p>
        @endforelse
        <form method="POST" action="{{ route('unit.requests.comment', [$unit, $r]) }}" class="form">
            @csrf
            <label class="field">Message
                <textarea name="body" required maxlength="3000" rows="3"></textarea>
            </label>
            <label class="login__remember"><input type="checkbox" name="internal" value="1">Team note only (not sent to them)</label>
            <div><button type="submit" class="btn btn--navy">Send</button></div>
        </form>
    </section>
@endsection

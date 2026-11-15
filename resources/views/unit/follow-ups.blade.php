@extends('layouts.unit', ['title' => 'Follow-up', 'active' => 'follow-ups'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Follow-up</h1>
            <span class="page-head__sub">People in {{ $unit->name }} the church is trying to reach: new members to welcome, and anyone who has missed {{ (int) config('church.care.absent_after') }} Sunday services in a row. A call from you means a lot. Note how it went so the care team knows.</span>
        </div>
    </header>

    <section class="box box--flush">
        <div class="box__head"><h2>To reach <span class="pill pill--info">{{ $open->count() }}</span></h2></div>
        @forelse ($open as $f)
            <article @class(['prayer-card', 'prayer-card--unread' => $f->isOverdue()])>
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $f->member->name }}</span>
                    <span class="pill {{ $f->reason === 'absent' ? 'pill--warn' : 'pill--info' }}">{{ $f->reasonLabel() }}</span>
                    <span class="prayer-card__when">Since {{ $f->created_at->format('j M') }}</span>
                </div>
                @if ($f->reason === 'other')
                    <p class="prayer-card__text">The pastors are caring for {{ $f->member->firstName() }}. A friendly call from you is still welcome.</p>
                @elseif ($f->note)
                    <p class="prayer-card__text">{{ $f->note }}</p>
                @endif
                <span class="prayer-card__contact">{{ $f->member->phone ?? 'No phone' }} · {{ $f->assignee ? $f->assignee->name.' from the care team is on it too' : 'Not yet with anyone on the care team' }}</span>
                @if ($f->logLines())
                    <ul class="check-list" style="margin: 0">
                        @foreach ($f->logLines() as $line)<li class="box__note" style="margin: 0">{{ $line }}</li>@endforeach
                    </ul>
                @endif
                @if ($f->reason !== 'other' && $f->script())
                    <details class="training-edit"><summary class="box__link" style="cursor: pointer">What to say</summary><p class="box__note" style="margin: 6px 0 0">{{ $f->script() }}</p></details>
                @endif
                <div class="list-item__actions" style="flex-wrap: wrap">
                    @if ($f->member->phone)
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $f->member->phone) }}" class="btn btn--navy btn--xs">Call</a>
                        @if ($f->member->whatsappUrl())<a href="{{ $f->member->whatsappUrl() }}" class="btn btn--ghost btn--xs" target="_blank" rel="noopener">WhatsApp</a>@endif
                    @endif
                    <form method="POST" action="{{ route('unit.follow-ups.update', [$unit, $f]) }}" style="display: flex; gap: 6px; flex-wrap: wrap">
                        @csrf
                        <label class="sr-only" for="outcome-{{ $f->id }}">How it went with {{ $f->member->name }}</label>
                        <select id="outcome-{{ $f->id }}" name="outcome" class="select-sm">
                            @foreach ($outcomes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="sr-only" for="note-{{ $f->id }}">Note</label>
                        <input id="note-{{ $f->id }}" type="text" name="note" maxlength="255" placeholder="Note (optional)" class="select-sm" style="min-width: 160px">
                        <button type="submit" class="btn btn--navy btn--xs">Save</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="empty-state">Nobody in {{ $unit->name }} needs following up right now. 🙌</p>
        @endforelse
    </section>

    @if ($done->isNotEmpty())
        <section class="box">
            <h2>Reached in the last 30 days</h2>
            <ul class="check-list">
                @foreach ($done as $f)
                    <li class="is-done">✓ {{ $f->member->name }} <span class="box__note" style="margin: 0">· {{ \App\Models\FollowUp::OUTCOMES[$f->outcome] ?? 'Done' }} {{ $f->done_at->format('j M') }}@if ($f->doer) by {{ $f->doer->name }}@endif</span></li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection

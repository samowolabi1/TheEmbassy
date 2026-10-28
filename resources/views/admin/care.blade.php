@extends('layouts.admin', ['title' => 'Follow-up & care', 'active' => 'care'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Follow-up &amp; care</h1>
            <span class="page-head__sub">First-timers are called within {{ (int) config('church.care.first_timer_days') }} days, new members are welcomed, and anyone who misses {{ (int) config('church.care.absent_after') }} Sundays in a row is checked on.@if ($overdue) <strong style="color: #a6531a">{{ $overdue }} overdue.</strong>@endif</span>
        </div>
        <div class="actions"><a href="#new-follow-up" class="btn btn--gold"><x-icon name="plus" :size="16" />Add a follow-up</a></div>
    </header>

    <nav class="tabs" aria-label="Follow-ups">
        @foreach ($tabs as $key => $t)
            <a href="{{ route('admin.care.index', ['tab' => $key]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $t['label'] }}@if ($t['count'] !== null) <span>{{ $t['count'] }}</span>@endif</a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($followUps as $f)
            @php($member = $f->member)
            <article @class(['prayer-card', 'prayer-card--unread' => $f->isOverdue()])>
                <div class="prayer-card__head">
                    @if ($member)
                        <a href="{{ route('admin.members.show', $member) }}" class="prayer-card__who">{{ $f->personName() }}</a>
                    @else
                        <span class="prayer-card__who">{{ $f->personName() }}</span>
                    @endif
                    <span class="pill {{ $f->reason === 'absent' ? 'pill--warn' : 'pill--info' }}">{{ $f->reasonLabel() }}</span>
                    @if ($f->done_at)
                        <span class="pill pill--ok">{{ \App\Models\FollowUp::OUTCOMES[$f->outcome] ?? 'Done' }}</span>
                    @elseif ($f->isOverdue())
                        <span class="pill pill--warn">Overdue</span>
                    @endif
                    <span class="prayer-card__when">{{ $f->done_at ? 'Done '.$f->done_at->format('j M') : 'By '.$f->due_on->format('D j M') }}</span>
                </div>
                @if ($f->note)<p class="prayer-card__text">{{ $f->note }}</p>@endif
                <span class="prayer-card__contact">
                    {{ $f->phone() ?? 'No phone' }}
                    @if ($member?->unit) · {{ $member->unit->name }}@endif
                    @if ($member?->fellowship) · {{ $member->fellowship->name }}@endif
                    @if ($f->firstTimer?->invited_by) · invited by {{ $f->firstTimer->invited_by }}@endif
                </span>
                @if ($f->done_at && ($f->outcome_note || $f->doer))
                    <span class="prayer-card__contact"><strong>{{ $f->doer?->name ?? 'Automatically' }}:</strong> {{ $f->outcome_note }}</span>
                @endif
                @unless ($f->done_at)
                    <div class="list-item__actions" style="flex-wrap: wrap">
                        @if ($f->phone())
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $f->phone()) }}" class="btn btn--navy btn--xs">Call</a>
                            @php($wa = $member?->whatsappUrl() ?? (new \App\Models\Member(['phone' => $f->phone()]))->whatsappUrl())
                            @if ($wa)<a href="{{ $wa }}" class="btn btn--ghost btn--xs" target="_blank" rel="noopener">WhatsApp</a>@endif
                        @endif
                        <form method="POST" action="{{ route('admin.care.assign', $f) }}" style="display: flex; gap: 6px">
                            @csrf
                            <label class="sr-only" for="assign-{{ $f->id }}">Assign {{ $f->personName() }}</label>
                            <select id="assign-{{ $f->id }}" name="assigned_to" class="select-sm">
                                <option value="">Not assigned</option>
                                @foreach ($carers as $carer)
                                    <option value="{{ $carer->id }}" @selected($f->assigned_to === $carer->id)>{{ $carer->is(auth()->user()) ? 'Me' : $carer->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn--ghost btn--xs">Assign</button>
                        </form>
                        <form method="POST" action="{{ route('admin.care.complete', $f) }}" style="display: flex; gap: 6px; flex-wrap: wrap">
                            @csrf
                            <label class="sr-only" for="outcome-{{ $f->id }}">How it went</label>
                            <select id="outcome-{{ $f->id }}" name="outcome" class="select-sm">
                                @foreach ($outcomes as $key => $label)
                                    @continue($key === 'returned')
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <label class="sr-only" for="note-{{ $f->id }}">Note</label>
                            <input id="note-{{ $f->id }}" type="text" name="outcome_note" maxlength="255" placeholder="Note (optional)" class="select-sm" style="min-width: 160px">
                            <button type="submit" class="btn btn--navy btn--xs">Done</button>
                        </form>
                    </div>
                @endunless
            </article>
        @empty
            <p class="empty-state">{{ $tab === 'mine' ? 'Nothing assigned to you. Pick some up from "Not assigned".' : 'Nothing here.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $followUps])
    </section>

    <section class="box" id="new-follow-up">
        <h2>Add a follow-up</h2>
        <form method="POST" action="{{ route('admin.care.store') }}" class="form">
            @csrf
            <div class="form-row">
                <label class="field">Member
                    <select name="member_id" required>
                        <option value="">Choose…</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}" @selected((int) old('member_id') === $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                    @error('member_id') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">By
                    <input type="date" name="due_on" value="{{ old('due_on', today()->addDays(2)->toDateString()) }}" min="{{ today()->toDateString() }}" required>
                </label>
                <label class="field">Who
                    <select name="assigned_to">
                        <option value="">Not assigned</option>
                        @foreach ($carers as $carer)
                            <option value="{{ $carer->id }}">{{ $carer->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="field">Why
                <input type="text" name="note" value="{{ old('note') }}" required maxlength="255" placeholder="e.g. Lost her job; check in and pray with her">
                @error('note') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <div><button type="submit" class="btn btn--navy">Add</button></div>
        </form>
    </section>
@endsection

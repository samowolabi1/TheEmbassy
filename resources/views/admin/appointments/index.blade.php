@extends('layouts.admin', ['title' => 'Visits with a pastor', 'active' => 'appointments'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Visits with a pastor</h1>
            <span class="page-head__sub">Members book from their account at the times set under Visiting hours. Confirm, move or decline each request; they're told by email and on their phone.</span>
        </div>
        <div class="actions"><a href="{{ route('admin.appointments.hours') }}" class="btn btn--ghost">Visiting hours &amp; time off</a></div>
    </header>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Appointments">
            @foreach (\App\Http\Controllers\Admin\AppointmentController::TABS as $key => $label)
                <a href="{{ route('admin.appointments.index', ['tab' => $key, 'pastor' => $pastorFilter]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $label }}@isset($counts[$key]) <span>{{ $counts[$key] }}</span>@endisset</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.appointments.index') }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="sr-only" for="pastor-filter">Pastor</label>
            <select id="pastor-filter" name="pastor" class="select-sm" onchange="this.form.submit()">
                <option value="all">All pastors</option>
                @foreach ($pastors as $p)
                    <option value="{{ $p->id }}" @selected($pastorFilter === (string) $p->id)>{{ $p->communityName() }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <section class="box box--flush">
        @forelse ($appointments as $a)
            <article class="prayer-card">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $a->member->name }}</span>
                    <span class="pill {{ match ($a->status) { 'requested' => 'pill--gold', 'confirmed' => 'pill--ok', 'completed' => 'pill--info', default => 'pill--draft' } }}">{{ \Illuminate\Support\Str::before($a->statusLabel(), ' to') }}</span>
                    <span class="prayer-card__when">{{ $a->reference }}</span>
                </div>
                <p class="prayer-card__text" style="margin: 0"><strong>{{ $a->when() }}</strong> · {{ $a->starts_at->diffInMinutes($a->ends_at) }} min · {{ \App\Models\Appointment::MODES[$a->mode] }} · with {{ $a->pastor?->communityName() }}</p>
                <p class="prayer-card__text" style="margin: 0">{{ \App\Models\Appointment::TOPICS[$a->topic] ?? $a->topic }}@if ($a->member->phone) · <a href="tel:{{ preg_replace('/\s+/', '', $a->member->phone) }}">{{ $a->member->phone }}</a>@endif</p>
                @if ($a->note)
                    @if ($a->noteVisibleTo(auth()->user()))
                        <p class="report-quote" style="margin: 0; white-space: pre-line">{{ $a->note }}</p>
                    @else
                        <p class="box__note" style="margin: 0">The member left a private note for the pastor.</p>
                    @endif
                @endif
                @if ($a->staff_note)
                    <p class="box__note" style="margin: 0">Told the member: "{{ $a->staff_note }}"</p>
                @endif

                @if ($a->isActive() && $a->ends_at->isFuture())
                    <details class="list-item--edit" @if ($errors->any() && old('appointment_id') == $a->id) open @endif>
                        <summary class="btn btn--navy btn--xs" style="display: inline-flex">{{ $a->status === 'requested' ? 'Confirm or move' : 'Change time or details' }}</summary>
                        <form method="POST" action="{{ route('admin.appointments.confirm', $a) }}" class="form" style="margin-top: 12px">
                            @csrf
                            <input type="hidden" name="appointment_id" value="{{ $a->id }}">
                            <div class="form-row">
                                <label class="field">Time
                                    <input type="datetime-local" name="starts_at" value="{{ $a->starts_at->format('Y-m-d\TH:i') }}">
                                    @error('starts_at') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="field">Call link (for a call)
                                    <input type="url" name="call_link" value="{{ $a->call_link }}" maxlength="500" placeholder="https://meet.google.com/…">
                                </label>
                            </div>
                            <label class="field">Message to {{ \App\Models\Member::firstNameOf($a->member->name) }} (optional)
                                <input type="text" name="staff_note" value="{{ $a->staff_note }}" maxlength="300" placeholder="e.g. Come to the pastor's office behind the main hall.">
                            </label>
                            <div><button type="submit" class="btn btn--gold btn--xs">{{ $a->status === 'requested' ? 'Confirm' : 'Save and tell them' }}</button></div>
                        </form>
                    </details>
                    <details class="list-item--edit">
                        <summary class="btn btn--ghost btn--xs" style="display: inline-flex">Decline</summary>
                        <form method="POST" action="{{ route('admin.appointments.decline', $a) }}" class="form" style="margin-top: 12px">
                            @csrf
                            <label class="field">Why (they'll see this)
                                <input type="text" name="reason" required maxlength="300" placeholder="e.g. The pastor is travelling that week; please choose another day.">
                            </label>
                            <div><button type="submit" class="btn btn--danger btn--xs">Decline</button></div>
                        </form>
                    </details>
                @elseif ($a->status === 'confirmed' && $a->starts_at->isPast())
                    <div class="list-item__actions">
                        <form method="POST" action="{{ route('admin.appointments.finish', $a) }}">
                            @csrf
                            <button type="submit" name="outcome" value="completed" class="btn btn--navy btn--xs">It happened</button>
                            <button type="submit" name="outcome" value="missed" class="btn btn--ghost btn--xs">They didn't come</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <p class="empty-state">{{ $tab === 'requests' ? 'No requests waiting.' : 'Nothing here.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $appointments])
    </section>
@endsection

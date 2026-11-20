@extends('layouts.admin', ['title' => $application->name, 'active' => 'empowerment'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.empowerment.show', $programme) }}" class="box__link">← {{ $programme->title }}</a>
            <h1>{{ $application->name }}</h1>
            <span class="page-head__sub">{{ $application->reference }} · applied {{ $application->created_at->format('j M Y, g:i A') }} · <span class="pill pill--info">{{ $application->statusLabel() }}</span></span>
        </div>
        <div class="actions">
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $application->phone) }}" class="btn btn--navy">Call</a>
            @if ($application->whatsappUrl())<a href="{{ $application->whatsappUrl() }}" class="btn btn--outline" target="_blank" rel="noopener">WhatsApp</a>@endif
        </div>
    </header>

    <div class="row" style="align-items: flex-start">
        <section class="box grow">
            <h2>About them</h2>
            <dl class="detail-list">
                <dt>Phone</dt><dd>{{ $application->phone }}</dd>
                <dt>Email</dt><dd>{{ $application->email ?? '—' }}</dd>
                <dt>Gender</dt><dd>{{ ucfirst((string) $application->gender) ?: '—' }}</dd>
                <dt>Age</dt><dd>{{ $application->age() !== null ? $application->age().' (born '.$application->date_of_birth->format('j M Y').')' : '—' }}</dd>
                <dt>Lives in</dt><dd>{{ $application->location ?? '—' }}</dd>
                <dt>Member</dt><dd>@if ($application->member)<a href="{{ route('admin.members.show', $application->member) }}">{{ $application->member->name }}</a>@else Not on the register @endif</dd>
                <dt>Choices</dt><dd>@foreach ($application->choices as $c){{ $c->pivot->rank }}. {{ $c->name }}<br>@endforeach</dd>
            </dl>
            <h2 style="margin-top: 20px">Their answers</h2>
            <dl class="answer-list">
                @forelse ($questions as $q)
                    <div><dt>{{ $q->label }}</dt><dd>{{ $q->answerText($application->answers[$q->key()] ?? null) ?: '—' }}</dd></div>
                @empty
                    <p class="box__note">The form has no extra questions.</p>
                @endforelse
            </dl>
        </section>

        <section class="box side-col" id="decide">
            <h2>Decision</h2>
            <form method="POST" action="{{ route('admin.empowerment.decide', [$programme, $application]) }}" class="form">
                @csrf
                <label class="field">Status
                    <select name="status">
                        @foreach (\App\Models\EmpowermentApplication::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected($application->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Accepted for <x-tip text="When you accept someone, choose which choice they're accepted for. Their first choice is used if you leave it." /></span>
                    <select name="empowerment_track_id">
                        <option value="">Their first choice</option>
                        @foreach ($tracks as $t)
                            <option value="{{ $t->id }}" @selected($application->empowerment_track_id === $t->id)>{{ $t->name }}{{ $application->choices->contains($t) ? ' (chose it)' : '' }}{{ $t->slots ? ' · '.max(0, $t->slots - $t->accepted_count).' places left' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">Note for the team (private)
                    <textarea name="admin_note" rows="3" maxlength="2000">{{ old('admin_note', $application->admin_note) }}</textarea>
                </label>
                <label class="login__remember"><input type="checkbox" name="notify" value="1" checked>Let them know by email (and WhatsApp if it's on)</label>
                <button type="submit" class="btn btn--navy" style="align-self: flex-start">Save</button>
                @if ($application->decider)<span class="field__hint">Last changed by {{ $application->decider->name }}, {{ $application->decided_at->format('j M Y') }}.</span>@endif
            </form>
        </section>
    </div>
@endsection

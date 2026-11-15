@extends('layouts.unit', ['title' => 'Members', 'active' => 'members'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Members</h1>
            <span class="page-head__sub">{{ $members->count() }} in {{ $unit->name }} · everyone in a unit is a worker. {{ $members->filter->needsTrainingAnswers()->count() }} still to answer their training, {{ $members->filter(fn ($m) => ! $m->needsTrainingAnswers() && $m->missingRequirements())->count() }} with classes still to do.</span>
        </div>
    </header>

    <section class="box box--flush">
        <div class="table-wrap">
            <table class="table" style="min-width: 900px">
                <thead><tr><th>NAME</th><th>ROLE <x-tip text="Everyone serving in a unit is a worker. Ministers are made by a pastor." /></th><th>TRAINING <x-tip text="Workers need the baptismal class, believers' class and workers' in training class. Ministers also need School of Disciples (SOD) and Bible college. Press Update to mark each one done or not yet." /></th><th>PHONE</th><th>FELLOWSHIP</th><th>LAST AT CHURCH</th><th class="num"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($members as $m)
                        <tr>
                            <td><span style="font-weight: 700">{{ $m->name }}</span>@if ($unit->isLedBy($m)) <span class="pill pill--info">{{ $unit->hod_member_id === $m->id ? 'HOD' : 'Assistant' }}</span>@endif
                                @if ($m->units->count() > 1)<span class="training-status" style="display: block">Also in {{ $m->units->where('id', '!=', $unit->id)->pluck('name')->join(', ') }}</span>@endif</td>
                            <td>
                                <span @class(['pill', 'pill--live' => $m->rank === 'minister', 'pill--ok' => $m->rank === 'worker', 'pill--draft' => $m->rank === 'member'])>{{ $m->rankLabel() }}</span>
                            </td>
                            <td style="max-width: 260px">
                                @include('unit.partials.training-status', ['m' => $m])
                                <details class="training-edit">
                                    <summary class="btn btn--ghost btn--xs">Update<span class="sr-only"> training for {{ $m->name }}</span></summary>
                                    <form method="POST" action="{{ route('unit.members.training', [$unit, $m]) }}" class="form" style="gap: 10px; margin-top: 8px">
                                        @csrf
                                        <x-training-questions :rank="$m->rank === 'minister' ? 'minister' : 'worker'" :member="$m" />
                                        <button type="submit" class="btn btn--navy btn--xs" style="align-self: flex-start">Save</button>
                                    </form>
                                </details>
                            </td>
                            <td>@if ($m->phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $m->phone) }}">{{ $m->phone }}</a>@else — @endif</td>
                            <td>{{ $m->fellowship?->name ?? '—' }}</td>
                            <td style="color: var(--slate)">{{ $m->last_attended_on?->format('j M') ?? '—' }}</td>
                            <td class="num" style="white-space: nowrap">
                                @unless ($unit->isLedBy($m))
                                    <form method="POST" action="{{ route('unit.members.remove', [$unit, $m]) }}" data-confirm="Remove {{ $m->name }} from {{ $unit->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--ghost btn--xs">Remove<span class="sr-only"> {{ $m->name }}</span></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="empty-state">No members yet. Add someone below.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="box">
        <h2>Add a member</h2>
        <p class="box__note">Everyone in the unit is a worker, so tell us which of the workers' classes they've done. They're told they're now a worker.</p>
        <form method="POST" action="{{ route('unit.members.add', $unit) }}" class="form" style="max-width: 640px">
            @csrf
            <label class="field">Member
                <select name="member_id" required>
                    <option value="">Choose…</option>
                    @foreach ($others as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}{{ $m->units->isNotEmpty() ? ' (also in '.$m->units->pluck('name')->join(', ').')' : '' }}</option>
                    @endforeach
                </select>
                @error('member_id') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <x-training-questions rank="worker" />
            <button type="submit" class="btn btn--navy" style="align-self: flex-start">Add to {{ $unit->name }}</button>
        </form>
    </section>
@endsection

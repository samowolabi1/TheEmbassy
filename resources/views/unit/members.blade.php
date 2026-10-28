@extends('layouts.unit', ['title' => 'Members', 'active' => 'members'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Members</h1>
            <span class="page-head__sub">{{ $members->count() }} in {{ $unit->name }}</span>
        </div>
    </header>

    <section class="box box--flush">
        <div class="table-wrap">
            <table class="table" style="min-width: 640px">
                <thead><tr><th>NAME</th><th>PHONE</th><th>FELLOWSHIP</th><th>LAST AT CHURCH</th><th class="num"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($members as $m)
                        <tr>
                            <td style="font-weight: 700">{{ $m->name }}@if ($unit->isLedBy($m)) <span class="pill pill--info">{{ $unit->hod_member_id === $m->id ? 'HOD' : 'Assistant' }}</span>@endif</td>
                            <td>@if ($m->phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $m->phone) }}">{{ $m->phone }}</a>@else — @endif</td>
                            <td>{{ $m->fellowship?->name ?? '—' }}</td>
                            <td style="color: var(--slate)">{{ $m->last_attended_on?->format('j M') ?? '—' }}</td>
                            <td class="num">
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
                        <tr><td colspan="5"><p class="empty-state">No members yet. Add someone below.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="box">
        <h2>Add a member</h2>
        <form method="POST" action="{{ route('unit.members.add', $unit) }}" class="form" style="flex-direction: row; flex-wrap: wrap; align-items: flex-end">
            @csrf
            <label class="field" style="flex: 1 1 260px; min-width: 0">Member
                <select name="member_id" required>
                    <option value="">Choose…</option>
                    @foreach ($others as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}{{ $m->unit_id ? ' (moves from another unit)' : '' }}</option>
                    @endforeach
                </select>
                @error('member_id') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <button type="submit" class="btn btn--navy">Add to {{ $unit->name }}</button>
        </form>
    </section>
@endsection

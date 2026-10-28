@extends('layouts.unit', ['title' => 'Attendance', 'active' => 'attendance'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Attendance</h1>
            <span class="page-head__sub">Sundays (where attendance was taken) and unit meetings. ✓ present, · absent.</span>
        </div>
    </header>

    <section class="box box--flush">
        <div class="table-wrap">
            <table class="table attendance-grid">
                <thead>
                    <tr>
                        <th>MEMBER</th>
                        @foreach ($sundays as $s)<th class="num" title="{{ $s->label() }}">{{ $s->held_on->format('j/n') }}</th>@endforeach
                        @foreach ($meetings as $mt)<th class="num" title="{{ $mt->title }}" style="color: var(--bronze)">M {{ $mt->starts_at->format('j/n') }}</th>@endforeach
                        <th class="num">RATE</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $m)
                        @php
                            $sv = $atService[$m->id] ?? [];
                            $mtg = $atMeeting[$m->id] ?? [];
                            $total = $sundays->count() + $meetings->count();
                            $rate = $total ? round((count($sv) + count($mtg)) * 100 / $total) : null;
                        @endphp
                        <tr>
                            <td style="font-weight: 700; white-space: nowrap">{{ $m->name }}</td>
                            @foreach ($sundays as $s)<td class="num">{!! in_array($s->id, $sv) ? '<span style="color: var(--ok); font-weight: 800">✓</span>' : '<span style="color: var(--slate)">·</span>' !!}</td>@endforeach
                            @foreach ($meetings as $mt)<td class="num">{!! in_array($mt->id, $mtg) ? '<span style="color: var(--ok); font-weight: 800">✓</span>' : '<span style="color: var(--slate)">·</span>' !!}</td>@endforeach
                            <td class="num" @style(['color: #a6531a; font-weight: 800' => $rate !== null && $rate < 50])>{{ $rate === null ? '—' : $rate.'%' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2"><p class="empty-state">No members yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

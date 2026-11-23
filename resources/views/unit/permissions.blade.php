@extends('layouts.unit', ['title' => 'Permission', 'active' => 'permissions'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Permission</h1>
            <span class="page-head__sub">Workers in {{ $unit->name }} who won't be in church or will be late, and why. The pastors see these too. Whoever answers, the worker is told straight away.</span>
        </div>
    </header>

    <section class="box box--flush">
        <div class="box__head"><h2>Coming up</h2></div>
        @forelse ($upcoming as $p)
            @include('partials.permission-card', ['p' => $p, 'action' => route('unit.permissions.answer', [$unit, $p])])
        @empty
            <p class="box__note" style="padding: 0 20px 20px">No one has asked for permission for the coming services.</p>
        @endforelse
    </section>

    @if ($past->isNotEmpty())
        <section class="box box--flush">
            <div class="box__head"><h2>The last two months</h2></div>
            @foreach ($past as $p)
                @include('partials.permission-card', ['p' => $p, 'action' => route('unit.permissions.answer', [$unit, $p])])
            @endforeach
        </section>
    @endif
@endsection

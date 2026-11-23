@extends('layouts.admin', ['title' => 'Workers\' permission', 'active' => 'permissions'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Workers' permission</h1>
            <span class="page-head__sub">Workers who won't be in church or will be coming late, with their reasons. Their HOD sees the same requests; whoever answers, the worker is told.</span>
        </div>
    </header>

    <nav class="tabs" aria-label="Which requests">
        <a href="{{ route('admin.permissions.index') }}" class="tab" @if ($show === 'upcoming') aria-current="page" @endif>Coming up @if ($pending)<span>{{ $pending }}</span>@endif</a>
        <a href="{{ route('admin.permissions.index', ['show' => 'past']) }}" class="tab" @if ($show === 'past') aria-current="page" @endif>Past</a>
    </nav>

    <section class="box box--flush">
        @forelse ($requests as $p)
            @include('partials.permission-card', ['p' => $p, 'action' => route('admin.permissions.answer', $p), 'showUnits' => true])
        @empty
            <p class="box__note" style="padding: 20px">{{ $show === 'past' ? 'No past requests yet.' : 'No one has asked for permission for the coming services.' }}</p>
        @endforelse
    </section>
    {{ $requests->links() }}
@endsection

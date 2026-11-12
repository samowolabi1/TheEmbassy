@extends('layouts.unit', ['title' => 'Welfare packages', 'active' => 'packages'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Welfare packages</h1>
            <span class="page-head__sub">Packages members apply for from their account, such as food packages or back-to-school support.</span>
        </div>
    </header>

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Packages</h2></div>
            @forelse ($packages as $p)
                <a href="{{ route('unit.welfare.packages.show', [$unit, $p]) }}" class="list-item" style="color: var(--ink)">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $p->title }}</span>
                        <span class="list-item__meta">{{ $p->opens_at->format('j M') }}–{{ $p->closes_at->format('j M Y') }} · {{ $p->applications_count }} applied · {{ $p->given_count }}{{ $p->slots ? ' of '.$p->slots : '' }} approved</span>
                    </span>
                    @if ($p->pending_count) <span class="pill pill--gold">{{ $p->pending_count }} to review</span> @endif
                    @if (! $p->is_published) <span class="pill pill--draft">Hidden</span>
                    @elseif ($p->isOpen()) <span class="pill pill--live">Open</span>
                    @elseif ($p->opens_at->isFuture()) <span class="pill pill--scheduled">Opens {{ $p->opens_at->format('j M') }}</span>
                    @else <span class="pill">Closed</span> @endif
                </a>
            @empty
                <p class="empty-state">No packages yet.</p>
            @endforelse
        </section>

        <section class="box side-col">
            <h2>New package</h2>
            <form method="POST" action="{{ route('unit.welfare.packages.store', $unit) }}" class="form">
                @csrf
                @include('unit.welfare.package-fields')
                <div><button type="submit" class="btn btn--gold">Save package</button></div>
            </form>
        </section>
    </div>
@endsection

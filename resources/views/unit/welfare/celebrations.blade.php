@extends('layouts.unit', ['title' => 'Celebrations', 'active' => 'celebrations'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Celebrations</h1>
            <span class="page-head__sub">Birthdays and wedding anniversaries in the next 30 days</span>
        </div>
    </header>

    <section class="box box--flush">
        @forelse ($events as $e)
            @php($m = $e['member'])
            <div class="list-item">
                <span class="avatar">{{ $m->initials() }}</span>
                <span class="list-item__main">
                    <span class="list-item__title">{{ $m->name }}</span>
                    <span class="list-item__meta">{{ $e['label'] }}{{ $e['years'] ? ' ('.$e['years'].' years)' : '' }} · {{ $e['days'] === 0 ? 'Today' : ($e['days'] === 1 ? 'Tomorrow' : $e['date']->format('D j M')) }}{{ $m->unit ? ' · '.$m->unit->name : '' }}</span>
                </span>
                @if ($m->whatsappUrl())
                    @php($greeting = $e['label'] === 'Birthday' ? 'Happy birthday, '.\Illuminate\Support\Str::before($m->name, ' ').'! The whole '.config('church.short_name').' family celebrates you today. God bless you!' : 'Happy wedding anniversary, '.\Illuminate\Support\Str::before($m->name, ' ').'! Celebrating God\'s faithfulness in your home. From all of us at '.config('church.short_name').'.')
                    <a href="{{ $m->whatsappUrl() }}?text={{ rawurlencode($greeting) }}" class="btn btn--ghost btn--xs" target="_blank" rel="noopener">Send greeting</a>
                @endif
            </div>
        @empty
            <p class="empty-state">No birthdays or anniversaries in the next 30 days. Members add theirs on their account page.</p>
        @endforelse
    </section>
@endsection

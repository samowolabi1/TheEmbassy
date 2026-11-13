@extends('layouts.admin', ['title' => 'Feedback · '.$service->label(), 'active' => 'feedback'])

@php($faces = [1 => '😞 Poor', 2 => '🙁 Fair', 3 => '😐 Good', 4 => '🙂 Very good', 5 => '😍 Excellent'])
@php($total = max(1, $feedback->count()))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.feedback.index') }}" class="box__link">← Feedback</a>
            <h1>{{ $service->label() }} · {{ $service->held_on->format('l j F Y') }}</h1>
            <span class="page-head__sub">{{ number_format($feedback->avg('rating'), 1) }} out of 5 from {{ $feedback->count() }} {{ \Illuminate\Support\Str::plural('member', $feedback->count()) }}.</span>
        </div>
    </header>

    <div class="row">
        <section class="box" style="flex: 0 1 360px">
            <h2>Ratings</h2>
            @foreach ($ratings as $n => $count)
                <div class="budget-line">
                    <div class="budget-line__head"><span>{{ $faces[$n] }}</span><span>{{ $count }}</span></div>
                    <div class="meter"><span style="width: {{ round($count / $total * 100) }}%"></span></div>
                </div>
            @endforeach
        </section>
        <section class="box grow">
            <h2>What members said about each part</h2>
            @forelse ($tally as $key => $t)
                <div class="list-item" style="padding: 8px 0">
                    <span class="list-item__main"><span class="list-item__title">{{ \App\Models\ServiceFeedback::ASPECTS[$key] }}</span></span>
                    @if ($t['well'])<span class="pill pill--ok">👍 {{ $t['well'] }} went well</span>@endif
                    @if ($t['improve'])<span class="pill pill--warn">🔧 {{ $t['improve'] }} could be better</span>@endif
                </div>
            @empty
                <p class="empty-state">No parts of the service were picked out.</p>
            @endforelse
        </section>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Comments</h2></div>
        @forelse ($feedback->filter(fn ($f) => filled($f->comment)) as $f)
            <article class="prayer-card">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $f->from() }}</span>
                    <span class="pill pill--info">{{ $faces[$f->rating] }}</span>
                    <span class="prayer-card__when">{{ $f->created_at->diffForHumans() }}</span>
                </div>
                <p class="prayer-card__text" style="white-space: pre-line">{{ $f->comment }}</p>
            </article>
        @empty
            <p class="empty-state">No written comments.</p>
        @endforelse
    </section>
@endsection

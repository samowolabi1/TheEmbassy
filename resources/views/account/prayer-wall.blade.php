@extends('layouts.app', ['title' => 'Prayer wall', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account.prayer') }}" class="back-link"><x-icon name="chevron-left" :size="16" />Prayer</a>
        <span class="eyebrow">PRAYER WALL</span>
        <h1 class="display notices-hero__title">Pray for one another.</h1>
        <p class="lede" style="max-width: 680px">Requests members have shared with the church. Take a moment to pray, then tap "I prayed" so they know they're not alone. Only members can see this page.</p>
    </section>

    <section class="container section--tight section--last stack" style="gap: 16px">
        @forelse ($requests as $p)
            <article class="card prayer-wall-card" id="p{{ $p->id }}">
                <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap">
                    <strong>{{ $p->wallName() }}</strong>
                    <span class="field-hint">{{ $p->categoryLabel() ?? 'Prayer' }} · {{ $p->created_at->diffForHumans() }}</span>
                </div>
                <p style="margin: 0; white-space: pre-line">{{ $p->request }}</p>
                @if ($p->answered_at)
                    <p class="status-chip status-chip--ok" style="margin: 0">Answered 🎉{{ $p->answer_note ? ': '.$p->answer_note : '' }}</p>
                @endif
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap">
                    @if ($p->member_id === auth()->user()->member?->id)
                        <span class="field-hint">Your request</span>
                    @elseif (in_array($p->id, $prayed, true))
                        <span class="btn btn--outline btn--sm" aria-disabled="true">🙏 You prayed</span>
                    @else
                        <form method="POST" action="{{ route('account.prayer.pray', $p) }}">
                            @csrf
                            <button type="submit" class="btn btn--gold btn--sm">🙏 I prayed</button>
                        </form>
                    @endif
                    @if ($p->prayed_count)
                        <span class="field-hint">{{ $p->prayed_count }} {{ \Illuminate\Support\Str::plural('person', $p->prayed_count) }} prayed</span>
                    @endif
                </div>
            </article>
        @empty
            <p class="lede">No requests on the wall right now. <a href="{{ route('account.prayer') }}" class="accent-bronze">Share yours</a> if you'd like the church to pray with you.</p>
        @endforelse

        @if ($requests->hasPages())
            <nav class="site-pager" aria-label="More requests">
                @if ($requests->onFirstPage())
                    <span class="btn btn--outline btn--sm" aria-disabled="true">Newer</span>
                @else
                    <a href="{{ $requests->previousPageUrl() }}" class="btn btn--outline btn--sm" rel="prev">Newer</a>
                @endif
                <span class="site-pager__count">Page {{ $requests->currentPage() }} of {{ $requests->lastPage() }}</span>
                @if ($requests->hasMorePages())
                    <a href="{{ $requests->nextPageUrl() }}" class="btn btn--outline btn--sm" rel="next">Older</a>
                @else
                    <span class="btn btn--outline btn--sm" aria-disabled="true">Older</span>
                @endif
            </nav>
        @endif
    </section>
@endsection

@extends('layouts.admin', ['title' => $campaign->subject, 'active' => 'outreach'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.outreach.index') }}" class="box__link">← Message centre</a>
            <h1>{{ $campaign->subject }}</h1>
            <span class="page-head__sub">
                To {{ mb_strtolower(\App\Services\Messaging\Campaigns::AUDIENCES[$campaign->audience['type'] ?? 'members'] ?? '') }}
                · {{ $campaign->statusLabel() }}
                @if ($campaign->status === 'scheduled') for {{ $campaign->scheduled_at->format('l j M, g:i A') }} @endif
                @if ($campaign->finished_at) · finished {{ $campaign->finished_at->format('j M, g:i A') }} @endif
                · by {{ $campaign->creator?->name ?? 'staff' }}
            </span>
        </div>
        @if (in_array($campaign->status, ['scheduled', 'sending'], true))
            <div class="actions">
                <form method="POST" action="{{ route('admin.outreach.cancel', $campaign) }}" data-confirm="Stop this message? People who already got it keep it.">
                    @csrf
                    <button type="submit" class="btn btn--danger">{{ $campaign->status === 'scheduled' ? 'Cancel' : 'Stop sending' }}</button>
                </form>
            </div>
        @endif
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy"><span class="stat-tile__label">People</span><span class="stat-tile__num">{{ $campaign->recipients_count === null ? '—' : number_format($campaign->recipients_count) }}</span><span class="stat-tile__foot">{{ $campaign->status === 'sending' ? $campaign->progress().'% done' : ($campaign->recipients_count === null ? 'Counted when it starts' : 'reached') }}</span></div>
        @if ($campaign->uses('email'))<div class="stat-tile"><span class="stat-tile__label">Emails</span><span class="stat-tile__num">{{ number_format($campaign->email_sent) }}</span></div>@endif
        @if ($campaign->uses('whatsapp'))<div class="stat-tile"><span class="stat-tile__label">WhatsApp</span><span class="stat-tile__num">{{ number_format($campaign->whatsapp_sent) }}</span></div>@endif
        @if ($campaign->uses('push'))<div class="stat-tile"><span class="stat-tile__label">App notifications</span><span class="stat-tile__num">{{ number_format($campaign->push_sent) }}</span></div>@endif
        <div class="stat-tile"><span class="stat-tile__label">Couldn't reach</span><span class="stat-tile__num">{{ number_format($campaign->failed) }}</span></div>
    </div>

    <div class="row">
        <section class="box grow">
            <h2>Message</h2>
            <div style="white-space: pre-line; line-height: 1.6">{{ $campaign->body }}</div>
        </section>

        <section class="box box--flush side-col">
            <div class="box__head">
                <h2>Who got it</h2>
                <nav class="tabs" aria-label="Filter">
                    <a href="{{ route('admin.outreach.show', $campaign) }}" class="tab" @if (! $filter) aria-current="page" @endif>All</a>
                    <a href="{{ route('admin.outreach.show', [$campaign, 'show' => 'failed']) }}" class="tab" @if ($filter === 'failed') aria-current="page" @endif>Couldn't reach</a>
                </nav>
            </div>
            @forelse ($recipients as $r)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $r->name }}</span>
                        <span class="list-item__meta">{{ ucfirst($r->kind) }}@if ($r->channels) · {{ str_replace(['whatsapp', 'app'], ['WhatsApp', 'app'], str_replace(',', ', ', $r->channels)) }}@endif @if ($r->error) · {{ $r->error }}@endif</span>
                    </span>
                    <span @class(['pill', 'pill--ok' => $r->status === 'sent', 'pill--warn' => $r->status === 'failed', 'pill--draft' => $r->status === 'pending'])>{{ ['sent' => 'Sent', 'failed' => 'Not reached', 'pending' => 'Waiting'][$r->status] ?? $r->status }}</span>
                </div>
            @empty
                <p class="empty-state">{{ $campaign->recipients_count === null ? 'The list is made when it starts going out.' : 'Nobody here.' }}</p>
            @endforelse
            @include('admin.partials.pager', ['paginator' => $recipients])
        </section>
    </div>
@endsection

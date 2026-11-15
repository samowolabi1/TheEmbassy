@extends('layouts.admin', ['title' => 'Message centre', 'active' => 'outreach'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Message centre</h1>
            <span class="page-head__sub">Send a message to members, sellers on {{ config('church.brand.market_name') }}, staff or everyone, by email, WhatsApp or app notification. Choose exactly who it's for, and use {first_name} to greet each person by name.</span>
        </div>
        <div class="actions">
            <a href="{{ route('admin.outreach.contacts') }}" class="btn btn--ghost">Contacts</a>
            <a href="{{ route('admin.outreach.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />New message</a>
        </div>
    </header>

    <div class="stats">
        <a href="{{ route('admin.outreach.create', ['to' => 'members']) }}" class="stat-tile"><span class="stat-tile__label">Approved members</span><span class="stat-tile__num">{{ number_format($counts['members']) }}</span><span class="stat-tile__foot">Message them →</span></a>
        <a href="{{ route('admin.outreach.create', ['to' => 'sellers']) }}" class="stat-tile"><span class="stat-tile__label">Sellers</span><span class="stat-tile__num">{{ number_format($counts['sellers']) }}</span><span class="stat-tile__foot">Message them →</span></a>
        <div class="stat-tile"><span class="stat-tile__label">Messages sent</span><span class="stat-tile__num">{{ number_format($counts['sent']) }}</span><span class="stat-tile__foot">in the last 30 days</span></div>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Messages</h2></div>
        @forelse ($campaigns as $c)
            <a href="{{ $c->status === 'draft' ? route('admin.outreach.edit', $c) : route('admin.outreach.show', $c) }}" class="list-item" style="color: inherit">
                <span class="list-item__main">
                    <span class="list-item__title">{{ $c->subject }}</span>
                    <span class="list-item__meta">
                        To {{ mb_strtolower(\App\Services\Messaging\Campaigns::AUDIENCES[$c->audience['type'] ?? 'members'] ?? 'members') }}
                        · {{ collect($c->channels)->map(fn ($w) => ['email' => 'email', 'whatsapp' => 'WhatsApp', 'push' => 'app'][$w] ?? $w)->join(', ') ?: 'no way chosen yet' }}
                        @if ($c->recipients_count !== null) · {{ number_format($c->recipients_count) }} people @endif
                        · {{ $c->status === 'scheduled' ? 'goes out '.$c->scheduled_at->format('D j M, g:i A') : ($c->finished_at ?? $c->updated_at)->diffForHumans() }}
                        · by {{ $c->creator?->name ?? 'staff' }}
                    </span>
                </span>
                <span @class(['pill', 'pill--live' => $c->status === 'sent', 'pill--scheduled' => in_array($c->status, ['scheduled', 'sending'], true), 'pill--draft' => in_array($c->status, ['draft', 'cancelled'], true)])>{{ $c->statusLabel() }}</span>
            </a>
        @empty
            <p class="empty-state">No messages yet. <a href="{{ route('admin.outreach.create') }}">Write the first one</a>.</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $campaigns])
    </section>
@endsection

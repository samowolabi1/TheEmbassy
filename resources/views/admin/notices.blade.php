@extends('layouts.admin', ['title' => 'Notice board', 'active' => 'notices'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Notice board</h1>
            <span class="page-head__sub">Paid notices from other churches and businesses. Nothing appears on the website until you approve it.</span>
        </div>
        <div class="actions">
            <a href="{{ route('notices.index') }}" class="btn btn--outline">View the board</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Notice income this month</span>
            <span class="stat-tile__num">₦{{ number_format($incomeThisMonth / 100) }}</span>
            <span class="stat-tile__foot">Paid through Paystack</span>
        </div>
        <a href="{{ route('admin.notices.index') }}" class="stat-tile">
            <span class="stat-tile__label">Waiting for review</span>
            <span class="stat-tile__num">{{ $counts['review'] }}</span>
            <span @class(['stat-tile__foot', 'text-warn' => $counts['review'] > 0])>{{ $counts['review'] ? 'Paid and waiting on you' : 'All caught up' }}</span>
        </a>
        <a href="{{ route('admin.notices.index', ['show' => 'live']) }}" class="stat-tile">
            <span class="stat-tile__label">Live now</span>
            <span class="stat-tile__num">{{ $counts['live'] }}</span>
            <span class="stat-tile__foot">On the website</span>
        </a>
    </div>

    <nav class="tabs" aria-label="Filter notices">
        @foreach (['review' => 'To review', 'live' => 'Live', 'expired' => 'Finished', 'rejected' => 'Rejected', 'unpaid' => 'Not paid'] as $key => $label)
            <a href="{{ route('admin.notices.index', ['show' => $key]) }}" class="tab" @if ($filter === $key) aria-current="page" @endif>{{ $label }} <span>{{ $counts[$key] }}</span></a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($notices as $notice)
            <article class="prayer-card" style="gap: 12px">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $notice->title }}</span>
                    <span class="pill pill--info">{{ $notice->category }}</span>
                    @if ($notice->featured)
                        <span class="pill pill--gold">Featured</span>
                    @endif
                    <span class="prayer-card__when">{{ $notice->created_at->diffForHumans() }}</span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 16px">
                    @if ($notice->imageUrl())
                        <img src="{{ $notice->imageUrl() }}" alt="Image for {{ $notice->title }}" style="width: 180px; max-width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 12px">
                    @endif
                    <p class="prayer-card__text" style="flex: 1 1 280px">{{ $notice->body }}</p>
                </div>
                <span class="prayer-card__contact">
                    <strong>{{ $notice->organisation }}</strong> ({{ $notice->advertiser_type }}) · {{ $notice->contact_name }} · {{ $notice->email }}@if ($notice->phone) · {{ $notice->phone }}@endif
                </span>
                @if ($notice->url)
                    <span class="prayer-card__contact">Link: <a href="{{ $notice->url }}" target="_blank" rel="noopener nofollow" style="overflow-wrap: anywhere">{{ $notice->url }}</a></span>
                @endif
                <span class="box__note">
                    {{ $notice->days }} days{{ $notice->featured ? ', featured' : '' }} · ₦{{ number_format($notice->amount / 100) }}
                    @if ($notice->paid_at) · paid {{ $notice->paid_at->format('j M, g:i A') }} @endif
                    @if ($notice->displayStatus() === 'live') · live until {{ $notice->ends_at->format('D j M') }} @endif
                    @if ($notice->displayStatus() === 'expired') · ended {{ $notice->ends_at->format('j M') }} @endif
                    @if ($notice->rejection_reason) · rejected: {{ $notice->rejection_reason }} @endif
                </span>

                @if (in_array($notice->status, [\App\Models\Notice::IN_REVIEW, \App\Models\Notice::REJECTED], true) && $notice->paid_at)
                    <div class="list-item__actions" style="flex-wrap: wrap">
                        <form method="POST" action="{{ route('admin.notices.approve', $notice) }}">
                            @csrf
                            <button type="submit" class="btn btn--navy btn--xs">Approve: live for {{ $notice->days }} days</button>
                        </form>
                        @if ($notice->status === \App\Models\Notice::IN_REVIEW)
                            <form method="POST" action="{{ route('admin.notices.reject', $notice) }}" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center">
                                @csrf
                                <label class="sr-only" for="reason-{{ $notice->id }}">Reason for rejecting</label>
                                <input id="reason-{{ $notice->id }}" type="text" name="reason" required maxlength="255" placeholder="Reason (the advertiser sees this)"
                                    style="height: 36px; min-width: 240px; border-radius: 999px; border: 1px solid var(--admin-line); padding: 0 14px; font-size: 13px">
                                <button type="submit" class="btn btn--danger btn--xs">Reject</button>
                            </form>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <p class="empty-state">Nothing here.</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $notices])
    </section>
    @error('reason')
        <p class="field-error" role="alert">{{ $message }}</p>
    @enderror
@endsection

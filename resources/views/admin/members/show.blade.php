@extends('layouts.admin', ['title' => $member->name, 'active' => 'members'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.members.index', ['tab' => $member->status]) }}" class="box__link">← Members</a>
            <h1 style="display: flex; align-items: center; gap: 14px"><span class="avatar" style="width: 52px; height: 52px; font-size: 18px">{{ $member->initials() }}</span>{{ $member->name }}</h1>
            <span class="page-head__sub">{{ $member->statusLabel() }}@if ($member->user) · has a login @endif</span>
        </div>
        <div class="actions">
            @if ($member->whatsappUrl())
                <a href="{{ $member->whatsappUrl() }}" class="btn btn--outline" target="_blank" rel="noopener">WhatsApp</a>
            @endif
            @if ($member->status === 'pending')
                <form method="POST" action="{{ route('admin.members.approve', $member) }}">
                    @csrf
                    <button type="submit" class="btn btn--navy">Approve</button>
                </form>
            @endif
            <a href="{{ route('admin.members.edit', $member) }}" class="btn btn--gold">Edit profile</a>
        </div>
    </header>

    <div class="row">
        <section class="box grow">
            <h2>Profile</h2>
            <dl class="detail-list">
                <dt>Phone</dt><dd>{{ $member->phone ?? '—' }}</dd>
                <dt>Email</dt><dd>{{ $member->email ?? '—' }}</dd>
                <dt>Unit</dt><dd>{{ $member->unit?->name ?? '—' }}</dd>
                <dt>Fellowship</dt><dd>{{ $member->fellowship?->name ?? '—' }}</dd>
                <dt>Member since</dt><dd>{{ $member->joined_on?->format('F Y') ?? '—' }}</dd>
                <dt>Birthday</dt><dd>{{ $member->birthday?->format('j F') ?? '—' }}</dd>
                <dt>Address</dt><dd>{{ $member->address ?? '—' }}</dd>
                <dt>Giving ({{ now()->year }})</dt><dd>₦{{ number_format($givingThisYear / 100) }}</dd>
                <dt>Shop</dt><dd>@if ($member->user?->shop)<a href="{{ route('market.shop', $member->user->shop) }}">{{ $member->user->shop->name }}</a>@else — @endif</dd>
            </dl>
            @if ($member->notes)
                <p class="box__note" style="white-space: pre-line">{{ $member->notes }}</p>
            @endif
        </section>

        <section class="box side-col">
            <h2>Milestones</h2>
            <ul class="check-list">
                @foreach (\App\Models\Member::MILESTONES as $field => $label)
                    <li @class(['is-done' => $member->{$field}])>{{ $member->{$field} ? '✓' : '○' }} {{ $label }}</li>
                @endforeach
            </ul>
            <h2>Tokens</h2>
            <p class="box__note" style="margin: 0">
                {{ $member->tokenPlan ? $member->tokenPlan->name.' · '.$member->tokenPlan->amountLabel().' a month' : 'No plan' }}<br>
                <strong>{{ $member->tokenStatus() }}</strong>@if ($member->tokenAmountOwed()) · ₦{{ number_format($member->tokenAmountOwed() / 100) }} owed @endif
            </p>
            @if (auth()->user()->canManage('finance') && $member->status === 'active' && $member->tokenPlan && ! $member->tokenPlan->isExempt())
                <a href="{{ route('admin.finance.index') }}#record-tokens" class="btn btn--ghost btn--xs" style="align-self: flex-start">Record a token payment</a>
            @endif
            <h2>Attendance</h2>
            @php($sundays = $member->recentSundays())
            <p class="box__note" style="margin: 0">@if ($sundays['of'])At <strong>{{ $sundays['attended'] }} of the last {{ $sundays['of'] }}</strong> Sundays where attendance was taken.@else No Sunday attendance taken yet.@endif</p>
            @forelse ($recentServices as $service)
                <span class="box__note" style="margin: 0">{{ $service->held_on->format('D j M') }} · {{ $service->label() }}</span>
            @empty
                <span class="box__note" style="margin: 0">No attendance marked yet.</span>
            @endforelse
        </section>
    </div>

    @if (auth()->user()->canManage('care'))
        <section class="box box--flush">
            <div class="box__head"><h2>Follow-ups</h2><a href="{{ route('admin.care.index') }}#new-follow-up" class="box__link">Add a follow-up</a></div>
            @forelse ($member->followUps()->with('doer')->latest()->take(8)->get() as $f)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $f->reasonLabel() }}@if ($f->note) · <span style="font-weight: 500">{{ $f->note }}</span>@endif</span>
                        <span class="list-item__meta">{{ $f->done_at ? (\App\Models\FollowUp::OUTCOMES[$f->outcome] ?? 'Done').' '.$f->done_at->format('j M Y').($f->doer ? ' by '.$f->doer->name : '').($f->outcome_note ? ': '.$f->outcome_note : '') : 'Open, due '.$f->due_on->format('j M') }}</span>
                    </span>
                </div>
            @empty
                <p class="empty-state">No follow-ups yet.</p>
            @endforelse
        </section>
    @endif

    <form method="POST" action="{{ route('admin.members.destroy', $member) }}" data-confirm="Remove {{ $member->name }} from the register? Their giving records stay." style="align-self: flex-start">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn--danger btn--xs">Remove from register</button>
    </form>
@endsection

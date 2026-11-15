@extends('layouts.admin', ['title' => $member->name, 'active' => 'members'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.members.index', ['tab' => $member->status]) }}" class="box__link">← Members</a>
            <h1 style="display: flex; align-items: center; gap: 14px"><span class="avatar" style="width: 52px; height: 52px; font-size: 18px">{{ $member->initials() }}</span>{{ $member->name }}</h1>
            <span class="page-head__sub">{{ $member->statusLabel() }} · {{ $member->rankLine() }}@if ($member->user) · has a login @endif</span>
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
                <dt>Role</dt>
                <dd>
                    <span @class(['pill', 'pill--live' => $member->rank === 'minister', 'pill--ok' => $member->rank === 'worker', 'pill--draft' => $member->rank === 'member'])>{{ $member->rankLabel() }}</span>
                    @if ($member->rank_changed_at) <span class="box__note" style="margin: 0">since {{ $member->rank_changed_at->format('j M Y') }}</span> @endif
                    @if ($member->status === 'active')
                        @php($ranks = app(\App\Services\People\Ranks::class))
                        <span style="display: inline-flex; gap: 6px; flex-wrap: wrap; margin-top: 6px">
                            @foreach (['member' => 'Make a member', 'worker' => 'Make a worker', 'minister' => 'Make a minister'] as $to => $label)
                                @if ($to !== $member->rank && $ranks->canChange(auth()->user(), $member, $to) && ! ($to === 'minister' && $member->rank === 'member') && ! ($to === 'worker' && ! $member->unit_id))
                                    <form method="POST" action="{{ route('admin.members.rank', $member) }}" @if ($to !== 'minister') data-confirm="{{ $label }}: {{ $member->name }}?" @endif>
                                        @csrf
                                        <input type="hidden" name="rank" value="{{ $to }}">
                                        <button type="submit" @class(['btn', 'btn--xs', 'btn--gold' => $to === 'minister', 'btn--ghost' => $to !== 'minister'])>{{ $label }}</button>
                                    </form>
                                @endif
                            @endforeach
                        </span>
                    @endif
                </dd>
                <dt>Fellowship</dt><dd>{{ $member->fellowship?->name ?? '—' }}</dd>
                <dt>Member since</dt><dd>{{ $member->joined_on?->format('F Y') ?? '—' }}</dd>
                <dt>Birthday</dt><dd>{{ $member->birthday?->format('j F') ?? '—' }}</dd>
                <dt>Address</dt><dd>{{ $member->address ?? '—' }}</dd>
                @if ($givingThisYear !== null)
                    <dt>Giving ({{ now()->year }})</dt><dd>₦{{ number_format($givingThisYear / 100) }}</dd>
                @endif
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
            <h2>{{ TokenName::title() }}</h2>
            <p class="box__note" style="margin: 0">
                @if ($member->user)
                    <strong>{{ TokenName::icon() }} {{ number_format($member->user->tokenBalance()) }}</strong> in their wallet
                @else
                    No login yet, so no {{ TokenName::one() }} wallet.
                @endif
            </p>
            @foreach ($tokenHistory as $t)
                @php($n = $t->amountFor($tokenAccount))
                <span class="box__note" style="margin: 0">{{ $t->created_at->format('j M') }} · {{ $t->description }} · <strong>{{ $n > 0 ? '+' : '−' }}{{ number_format(abs($n)) }}</strong></span>
            @endforeach
            @if ($member->user && auth()->user()->canManage('tokens'))
                <a href="{{ route('admin.tokens.index', ['member' => $member->id]) }}" class="btn btn--ghost btn--xs" style="align-self: flex-start">{{ auth()->user()->isSuperAdmin() ? 'Give or take '.TokenName::many() : TokenName::titleOne().' history' }}</a>
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

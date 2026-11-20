@extends('layouts.admin', ['title' => $member->name, 'active' => 'members'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.members.index', ['tab' => $member->status]) }}" class="box__link">← Members</a>
            <h1 style="display: flex; align-items: center; gap: 14px"><span class="avatar" style="width: 52px; height: 52px; font-size: 18px">{{ $member->initials() }}</span>{{ $member->name }}</h1>
            <span class="page-head__sub">{{ $member->statusLabel() }} · {{ $member->rankLine() }}@if ($member->user) · has a login @else · <a href="#login">no login yet</a>@endif</span>
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
                <dt>{{ $member->units->count() > 1 ? 'Units' : 'Unit' }}</dt><dd>{{ $member->unitNames() ?? '—' }}@foreach ($member->units as $u)@if ($u->isLedBy($member)) <span class="pill pill--info">{{ $u->hod_member_id === $member->id ? 'HOD' : 'Assistant' }} of {{ $u->name }}</span>@endif @endforeach</dd>
                <dt>Role</dt>
                <dd>
                    <span @class(['pill', 'pill--live' => $member->rank === 'minister', 'pill--ok' => $member->rank === 'worker', 'pill--draft' => $member->rank === 'member'])>{{ $member->rankLabel() }}</span>
                    @if ($member->rank_changed_at) <span class="box__note" style="margin: 0">since {{ $member->rank_changed_at->format('j M Y') }}</span> @endif
                    @if ($member->status === 'active' && auth()->user()->isPastor())
                        @if ($member->rank === 'worker')
                            <details class="training-edit" @if ($errors->has('training') || $errors->has('training.*') || $errors->has('rank')) open @endif>
                                <summary class="btn btn--gold btn--xs">Make a minister</summary>
                                <form method="POST" action="{{ route('admin.members.rank', $member) }}" class="form" style="gap: 10px; margin-top: 8px; max-width: 520px">
                                    @csrf
                                    <input type="hidden" name="rank" value="minister">
                                    <x-training-questions rank="minister" :member="$member" />
                                    @error('rank') <span class="field-error">{{ $message }}</span> @enderror
                                    <button type="submit" class="btn btn--navy btn--xs" style="align-self: flex-start">Make {{ $member->firstName() }} a minister</button>
                                </form>
                            </details>
                        @elseif ($member->rank === 'minister')
                            <form method="POST" action="{{ route('admin.members.rank', $member) }}" style="display: inline" data-confirm="{{ $member->name }} will no longer be a minister{{ $member->unit_id ? ' (they stay a worker in '.$member->unit->name.')' : '' }}. Continue?">
                                @csrf
                                <input type="hidden" name="rank" value="worker">
                                <button type="submit" class="btn btn--ghost btn--xs">No longer a minister</button>
                            </form>
                        @endif
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
            <h2 id="login">Login <x-tip text="How they sign in to the app: with their email or phone number and a password. Send a link to choose a password (it works for 7 days; send it again any time), or give a temporary password to pass on yourself. They choose their own the first time they sign in." /></h2>
            @if (session('tempPassword'))
                <div class="temp-password" role="status">
                    <span class="box__note" style="margin: 0">Temporary password for {{ $member->firstName() }}, shown only now:</span>
                    <code class="temp-password__code">{{ session('tempPassword') }}</code>
                    <span style="display: flex; gap: 8px; flex-wrap: wrap">
                        <button type="button" class="btn btn--ghost btn--xs" data-copy="{{ session('tempPassword') }}" data-copy-label="Copy">Copy</button>
                        @if ($member->whatsappUrl())
                            <a href="{{ $member->whatsappUrl() }}?text={{ rawurlencode('Hi '.$member->firstName().', here is your '.config('church.short_name').' login. Go to '.route('login').' and sign in with '.($member->user?->email ?: 'your phone number '.$member->phone).' and the password '.session('tempPassword').'. You will choose your own password after you sign in.') }}" class="btn btn--gold btn--xs" target="_blank" rel="noopener">Send on WhatsApp</a>
                        @endif
                    </span>
                </div>
            @endif
            @error('login') <p class="field-error" role="alert" style="margin: 0">{{ $message }}</p> @enderror
            <p class="box__note" style="margin: 0">
                @if (! $member->user)
                    No login yet. They'll sign in with {{ $member->email ? 'their email ('.$member->email.')' : '' }}{{ $member->email && $member->phone ? ' or ' : '' }}{{ $member->phone ? 'their phone number' : '' }}{{ ! $member->email && ! $member->phone ? 'an email or phone number: add one first' : '' }}.
                @elseif ($member->user->must_change_password)
                    Has a temporary password and hasn't signed in to choose their own yet.
                @elseif ($member->user->last_login_at)
                    Has a login. Last signed in {{ $member->user->last_login_at->diffForHumans() }}.
                @elseif ($member->user->invited_at)
                    Sent a link {{ $member->user->invited_at->diffForHumans() }}; hasn't signed in yet.
                @else
                    Has a login{{ $member->user->email ? ' ('.$member->user->email.')' : '' }}.
                @endif
            </p>
            @if ($member->email || $member->phone)
                <div style="display: flex; gap: 8px; flex-wrap: wrap">
                    @if ($member->user?->email ?? $member->email)
                        <form method="POST" action="{{ route('admin.members.login.invite', $member) }}">
                            @csrf
                            <button type="submit" class="btn btn--navy btn--xs">{{ $member->user ? 'Send a password link' : 'Send a link to set a password' }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.members.login.temporary', $member) }}" data-confirm="Give {{ $member->firstName() }} a new temporary password?{{ $member->user ? ' Their current password will stop working.' : '' }}">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--xs">Give a temporary password</button>
                    </form>
                </div>
            @endif
            <h2>Training</h2>
            <p style="margin: 0">@include('unit.partials.training-status', ['m' => $member])</p>
            <ul class="check-list">
                @foreach (\App\Models\Member::MILESTONES as $field => $label)
                    <li @class(['is-done' => $member->{$field}])>{{ $member->{$field} ? '✓' : '○' }} {{ $label }}@if (in_array($field, \App\Models\Member::REQUIREMENTS['worker'], true)) <span class="box__note" style="margin: 0">· workers</span>@elseif (in_array($field, \App\Models\Member::REQUIREMENTS['minister'], true)) <span class="box__note" style="margin: 0">· ministers</span>@endif</li>
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

        <section class="box box--flush">
            <div class="box__head"><h2>Visits</h2><a href="{{ route('admin.visits.index') }}#new-visit" class="box__link">Plan a visit</a></div>
            @forelse (\App\Models\Visit::where('member_id', $member->id)->with('visitors')->latest('scheduled_at')->take(6)->get() as $v)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $v->purposeLabel() }} · {{ $v->scheduled_at->format('j M Y') }}</span>
                        <span class="list-item__meta">{{ $v->status === 'done' ? (\App\Models\Visit::OUTCOMES[$v->outcome] ?? 'Done') : ucfirst($v->status) }} · {{ $v->visitors->pluck('name')->join(', ') }}@if ($v->report) · {{ \Illuminate\Support\Str::limit($v->report, 140) }}@endif</span>
                    </span>
                </div>
            @empty
                <p class="empty-state">No visits yet.</p>
            @endforelse
        </section>
    @endif

    <form method="POST" action="{{ route('admin.members.destroy', $member) }}" data-confirm="Remove {{ $member->name }} from the register? Their giving records stay." style="align-self: flex-start">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn--danger btn--xs">Remove from register</button>
    </form>
@endsection

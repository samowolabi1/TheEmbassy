@extends('layouts.app', ['title' => 'My account', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <span class="eyebrow">MY ACCOUNT</span>
        <h1 class="display notices-hero__title">Hi, {{ \Illuminate\Support\Str::before($member?->name ?? $user->name, ' ') }}.</h1>
        <div style="display: flex; flex-wrap: wrap; gap: 10px">
            @if ($member?->status === 'active')
                <a href="{{ route('check-in') }}" class="btn btn--gold btn--sm">Check in to a service</a>
            @endif
            @if ($member?->status === 'active')
                <a href="{{ route('account.welfare') }}" class="btn btn--outline btn--sm">Welfare</a>
            @endif
            @foreach ($ledUnits as $led)
                <a href="{{ route('unit.show', $led) }}" class="btn btn--navy btn--sm">Lead {{ $led->name }}</a>
            @endforeach
            @if ($user->isStaff())
                <a href="{{ route('admin.dashboard') }}" class="btn btn--navy btn--sm">Admin dashboard</a>
            @endif
            @can('run-shop')
                <a href="{{ route('vendor.products.index') }}" class="btn btn--outline btn--sm">My shop</a>
            @endcan
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn--outline btn--sm">Log out</button>
            </form>
        </div>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif

        @unless ($member)
            <div class="card auth" style="max-width: none; margin: 0">
                <h2 class="auth__title" style="font-size: 24px">You're not on the church register yet</h2>
                <p class="lede" style="margin: 0">Join to connect with a unit and a house fellowship, and to keep track of your giving.</p>
                <a href="{{ route('join') }}" class="btn btn--gold" style="align-self: flex-start">Join the family</a>
            </div>
        @else
            <div class="account-grid">
                <div class="card">
                    <h2>Membership</h2>
                    <span class="status-chip {{ $member->status === 'active' ? 'status-chip--ok' : '' }}">{{ $member->statusLabel() }}</span>
                    <dl class="facts">
                        <dt>Unit</dt><dd>{{ $member->unit?->name ?? 'Not yet' }}</dd>
                        <dt>Fellowship</dt><dd>{{ $member->fellowship?->name ?? 'Not yet' }}</dd>
                        @if ($member->joined_on)<dt>Member since</dt><dd>{{ $member->joined_on->format('F Y') }}</dd>@endif
                        @if ($member->last_attended_on)<dt>Last at church</dt><dd>{{ $member->last_attended_on->format('j M Y') }}</dd>@endif
                    </dl>
                    <ul class="mini-list" aria-label="Milestones">
                        @foreach (\App\Models\Member::MILESTONES as $field => $label)
                            <li><span>{{ $label }}</span><strong>{{ $member->{$field} ? '✓ Done' : 'Not yet' }}</strong></li>
                        @endforeach
                    </ul>
                </div>

                <div class="card">
                    <h2>Tokens</h2>
                    @if ($member->status !== 'active')
                        <p class="lede" style="margin: 0">Tokens start once your membership is confirmed.</p>
                    @elseif (! $member->tokenPlan || $member->tokenPlan->isExempt())
                        <p class="lede" style="margin: 0">You don't pay tokens on your plan.</p>
                    @else
                        <dl class="facts">
                            <dt>Plan</dt><dd>{{ $member->tokenPlan->name }} · {{ $member->tokenPlan->amountLabel() }} a month</dd>
                            <dt>Status</dt><dd>{{ $member->tokenStatus() }}</dd>
                            @if ($member->tokenAmountOwed())<dt>Owed</dt><dd>₦{{ number_format($member->tokenAmountOwed() / 100) }}</dd>@endif
                        </dl>
                        <a href="{{ route('account.tokens') }}" class="btn btn--gold" style="align-self: flex-start">Pay tokens</a>
                    @endif
                </div>

                <div class="card">
                    <h2>Recent giving</h2>
                    @if ($gifts->isEmpty())
                        <p class="lede" style="margin: 0">Nothing yet. Thank you for every seed you sow.</p>
                    @else
                        <ul class="mini-list">
                            @foreach ($gifts as $gift)
                                <li><span>{{ $gift->fund->name }} · {{ $gift->given_on->format('j M Y') }}</span><strong>₦{{ number_format($gift->amount / 100) }}</strong></li>
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ route('give') }}" class="btn btn--outline" style="align-self: flex-start">Give</a>
                </div>
            </div>

            @if ($member->unit && ($meetings->isNotEmpty() || $duties->isNotEmpty() || $unitMessages->isNotEmpty()))
                <div class="account-grid">
                    <div class="card">
                        <h2>{{ $member->unit->name }}</h2>
                        @if ($errors->has('meeting'))<p class="notice-alert" role="alert" style="margin: 0; background: #fdecea; color: #8a1c12">{{ $errors->first('meeting') }}</p>@endif
                        @forelse ($meetings as $meeting)
                            <div class="stack" style="gap: 6px">
                                <strong>{{ $meeting->title }}</strong>
                                <span class="field-hint">{{ $meeting->starts_at->format('D j M, g:i A') }}{{ $meeting->location ? ' · '.$meeting->location : '' }}</span>
                                @if ($meeting->online_url)<a href="{{ $meeting->online_url }}" class="btn btn--outline btn--sm" style="align-self: flex-start" target="_blank" rel="noopener">Join online</a>@endif
                                @if (in_array($meeting->id, $checkedInMeetings, true))
                                    <span class="status-chip status-chip--ok">✓ Checked in</span>
                                @elseif ($meeting->checkInIsOpen())
                                    <form method="POST" action="{{ route('meetings.check-in', $meeting) }}" style="display: flex; gap: 8px; flex-wrap: wrap">
                                        @csrf
                                        <label class="sr-only" for="meeting-code-{{ $meeting->id }}">Meeting code</label>
                                        <input id="meeting-code-{{ $meeting->id }}" type="text" name="code" inputmode="numeric" maxlength="7" required placeholder="Code" class="select-sm" style="height: 44px; border-radius: 12px; border: 1px solid var(--field-line); padding: 0 12px; width: 120px">
                                        <button type="submit" class="btn btn--navy btn--sm">Check in</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="field-hint">No meetings scheduled.</span>
                        @endforelse
                    </div>
                    @if ($duties->isNotEmpty())
                        <div class="card">
                            <h2>Your duties</h2>
                            <ul class="mini-list">
                                @foreach ($duties as $duty)
                                    <li><span>{{ $duty->service->starts_at?->format('D j M, g:i A') ?? $duty->service->held_on->format('D j M') }}</span><strong>{{ $duty->role }}</strong></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($unitMessages->isNotEmpty())
                        <div class="card">
                            <h2>From your unit</h2>
                            @foreach ($unitMessages as $um)
                                <div class="stack" style="gap: 4px"><strong>{{ $um->subject }}</strong><span class="field-hint" style="white-space: pre-line">{{ \Illuminate\Support\Str::limit($um->body, 220) }}</span></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('account.update') }}" class="card notice-form__main" style="flex: none">
                @csrf
                @method('PUT')
                <fieldset class="notice-form__group">
                    <legend>My details</legend>
                    <div class="grid-auto" style="--min: 220px; --gap: 14px">
                        <label class="field">Phone
                            <input type="tel" name="phone" value="{{ old('phone', $member->phone) }}" required maxlength="40">
                            @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Birthday
                            <input type="date" name="birthday" value="{{ old('birthday', $member->birthday?->toDateString()) }}" max="{{ today()->subDay()->toDateString() }}">
                            @error('birthday') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Wedding anniversary (optional)
                            <input type="date" name="anniversary" value="{{ old('anniversary', $member->anniversary?->toDateString()) }}" max="{{ today()->toDateString() }}">
                        </label>
                        <label class="field">Unit
                            <select name="unit_id">
                                <option value="">None</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" @selected((int) old('unit_id', $member->unit_id) === $unit->id)>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">House fellowship
                            <select name="fellowship_id">
                                <option value="">None</option>
                                @foreach ($fellowships as $fellowship)
                                    <option value="{{ $fellowship->id }}" @selected((int) old('fellowship_id', $member->fellowship_id) === $fellowship->id)>{{ $fellowship->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="field">Address or area
                        <input type="text" name="address" value="{{ old('address', $member->address) }}" maxlength="255">
                    </label>
                    <button type="submit" class="btn btn--navy" style="align-self: flex-start">Save details</button>
                </fieldset>
            </form>
        @endunless
    </section>
@endsection

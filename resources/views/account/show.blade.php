@extends('layouts.app', ['title' => 'My account', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <span class="eyebrow">MY ACCOUNT</span>
        <h1 class="display notices-hero__title">Hi, {{ \App\Models\Member::firstNameOf($member?->name ?? $user->name) }}.</h1>
        @if ($feedbackDue)
            <a href="{{ route('account.feedback', ['service' => $feedbackDue->id]) }}#service-{{ $feedbackDue->id }}" class="live-banner" style="background: var(--wash); color: var(--navy)">
                <span class="stack" style="gap: 2px">
                    <strong>How was {{ $feedbackDue->label() }}?</strong>
                    <span>Tell us in a minute: what blessed you and what we can do better.</span>
                </span>
                <span class="btn btn--navy btn--sm">Give feedback</span>
            </a>
        @endif
        @if ($birthday)
            @php
                $wishes = $birthday->message ? \App\Models\CommunityMessage::where('reply_to_id', $birthday->message->id)->whereNull('removed_at')->count() + \App\Models\CommunityReaction::where('community_message_id', $birthday->message->id)->count() : 0;
            @endphp
            <div class="birthday-banner" id="birthday">
                <img src="{{ $birthday->cardUrl() }}" alt="Your birthday card" class="birthday-banner__card" width="270" height="338">
                <div class="stack" style="gap: 10px">
                    <span class="eyebrow">🎂 {{ $member->hasBirthdayToday() ? 'TODAY' : 'YOUR BIRTHDAY' }}</span>
                    <h2 class="h2 h2--sm" style="margin: 0">Happy birthday, {{ $member->firstName() }}! 🎉</h2>
                    <p class="lede" style="margin: 0">The whole {{ config('church.short_name') }} family celebrates you. Here's your card to share.</p>
                    @if ($wishes)
                        <p style="margin: 0; font-weight: 700">{{ $wishes }} {{ \Illuminate\Support\Str::plural('wish', $wishes) }} and reactions in the community so far.</p>
                    @endif
                    <div style="display: flex; flex-wrap: wrap; gap: 8px">
                        <a href="{{ $birthday->cardUrl() }}" download="birthday-card.png" class="btn btn--gold btn--sm">Download card</a>
                        <a href="https://wa.me/?text={{ rawurlencode('It\'s my birthday! 🎉 '.$birthday->cardUrl()) }}" target="_blank" rel="noopener" class="btn btn--outline btn--sm">Share on WhatsApp</a>
                        @if ($birthday->message && ! $birthday->message->isRemoved())
                            <a href="{{ route('community.room', $birthday->message->room) }}#m{{ $birthday->message->id }}" class="btn btn--outline btn--sm">See the wishes</a>
                        @endif
                    </div>
                </div>
            </div>
        @endif
        @if ($member?->status === 'active')
            @foreach ($joinable as $service)
                <a href="{{ route('services.join', $service) }}" class="live-banner">
                    <span class="live-banner__dot" aria-hidden="true"></span>
                    <span class="stack" style="gap: 2px">
                        <strong>{{ $service->label() }} is on</strong>
                        <span>Join online and you'll be marked present</span>
                    </span>
                    <span class="btn btn--gold btn--sm">Join now</span>
                </a>
            @endforeach
            <nav class="app-tiles" aria-label="Quick links">
                <a href="{{ route('check-in') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">✅</span>Mark attendance</a>
                <a href="{{ $joinable->isNotEmpty() ? route('services.join', $joinable->first()) : route('watch') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">📺</span>Join online</a>
                <a href="{{ route('account.inbox') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">📣</span>Messages
                    @if ($unread) <span class="app-tile__badge"><span class="sr-only">unread: </span>{{ $unread > 99 ? '99+' : $unread }}</span> @endif
                </a>
                <a href="{{ route('account.welfare') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">🤲</span>Welfare
                    @if ($openPackages) <span class="app-tile__badge app-tile__badge--soft">{{ $openPackages }} open</span> @endif
                </a>
                <a href="{{ route('community.index') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">💬</span>Community</a>
                @if ($user->canUseTokens())
                <a href="{{ route('account.tokens') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">🪙</span>Tokens
                    <span class="app-tile__badge app-tile__badge--soft">{{ number_format($user->tokenBalance()) }}</span>
                </a>
                @endif
                <a href="{{ route('account.prayer') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">🙏</span>Prayer</a>
                <a href="{{ route('account.appointments') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">🗓️</span>See a pastor</a>
                <a href="{{ route('account.testimonies') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">🙌</span>Testimony</a>
                <a href="{{ route('library.index') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">📚</span>Library</a>
                <a href="{{ route('account.feedback') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">💡</span>Feedback</a>
                <a href="{{ route('give') }}" class="app-tile"><span class="app-tile__icon" aria-hidden="true">🎁</span>Give</a>
            </nav>
        @endif
        <div style="display: flex; flex-wrap: wrap; gap: 10px">
            @if ($member?->status === 'active' && $member->unit?->isMedia() && ! $member->unit->isLedBy($member))
                <a href="{{ route('unit.setup.index', $member->unit) }}" class="btn btn--navy btn--sm">Media team</a>
            @endif
            @foreach ($ledUnits as $led)
                <a href="{{ route('unit.show', $led) }}" class="btn btn--navy btn--sm">Lead {{ $led->name }}</a>
            @endforeach
            @if ($user->isStaff())
                <a href="{{ route('admin.dashboard') }}" class="btn btn--navy btn--sm">Admin dashboard</a>
            @endif
            @if ($ledUnits->isNotEmpty() || $user->isStaff())
                <a href="{{ route('media-requests.index') }}" class="btn btn--outline btn--sm">Ask the media team</a>
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
                        <p class="lede" style="margin: 0">Once your membership is confirmed you'll get welcome tokens, and earn more by coming to church, joining online, the Bible quiz and more.</p>
                    @else
                        <span class="token-balance"><span class="token-balance__coin" aria-hidden="true">🪙</span>{{ number_format($user->tokenBalance()) }} <span>{{ \Illuminate\Support\Str::plural('token', $user->tokenBalance()) }}</span></span>
                        <p class="lede" style="margin: 0">Free rewards for taking part. Spend them in the {{ config('church.brand.market_name') }} with sellers who accept them.</p>
                        <a href="{{ route('account.tokens') }}" class="btn btn--gold" style="align-self: flex-start">My tokens</a>
                    @endif
                </div>

                <div class="card">
                    <h2>Giving</h2>
                    <p class="lede" style="margin: 0">Tithes and offerings are by bank transfer; donations can also be made online. Thank you for every seed you sow.</p>
                    <a href="{{ route('give') }}" class="btn btn--outline" style="align-self: flex-start">How to give</a>
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
                <fieldset class="notice-form__group" id="details">
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
                    <label class="check"><input type="checkbox" name="notify_sms" value="1" @checked(old('notify_sms', $member->notify_sms))>Text me church announcements</label>
                    <button type="submit" class="btn btn--navy" style="align-self: flex-start">Save details</button>
                </fieldset>
            </form>

            @if ($member->status === 'active')
                <form method="POST" action="{{ route('account.birthday') }}" enctype="multipart/form-data" class="card notice-form__main" style="flex: none">
                    @csrf
                    @method('PUT')
                    <fieldset class="notice-form__group" id="birthday-card">
                        <legend>My birthday card</legend>
                        <p class="lede" style="margin: 0">On your birthday the church makes you a card, shares it in the community and celebrates you. Only the day is shown, never the year.</p>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-start">
                            @if ($member->birthday)
                                <a href="{{ route('account.birthday-card') }}" target="_blank" rel="noopener"><img src="{{ route('account.birthday-card') }}" alt="A preview of your birthday card" width="160" height="200" loading="lazy" style="border-radius: 12px; box-shadow: 0 6px 20px rgba(10, 29, 63, 0.15)"></a>
                            @endif
                            <div class="stack" style="gap: 12px; flex: 1 1 240px">
                                <label class="field">{{ $member->photo_path ? 'Change the photo on your card' : 'A photo for your card (optional)' }}
                                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                                    <span class="field-hint">A clear photo of your face. Without one, your card shows your initials.</span>
                                    @error('photo') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                @if ($member->photo_path)
                                    <label class="check"><input type="checkbox" name="remove_photo" value="1">Remove my photo</label>
                                @endif
                                <label class="check"><input type="checkbox" name="celebrate_birthday" value="1" @checked($member->celebrate_birthday)>Celebrate my birthday with the church (card in the community)</label>
                                @unless ($member->birthday)
                                    <span class="field-hint">Add your birthday in My details above to get a card.</span>
                                @endunless
                                <button type="submit" class="btn btn--navy" style="align-self: flex-start">Save</button>
                            </div>
                        </div>
                    </fieldset>
                </form>
            @endif

            <div class="card notice-form__main" style="flex: none">
                <h2>Notifications on this phone</h2>
                <p class="lede" style="margin: 0">Get church announcements as notifications on this phone or computer.</p>
                <div class="app-actions">
                    <button type="button" class="btn btn--outline" data-install aria-controls="install-hint" hidden>Install the app</button>
                    <button type="button" class="btn btn--navy" data-push-toggle data-on-label="Turn on notifications" data-off-label="Turn off notifications" hidden>Turn on notifications</button>
                    <span class="app-actions__status" data-push-status aria-live="polite"></span>
                </div>
                <p id="install-hint" class="field-hint" hidden>On iPhone: tap Share in Safari, then "Add to Home Screen". On Android: open the browser menu and choose "Install app".</p>
                <noscript><p class="field-hint">Notifications need JavaScript turned on.</p></noscript>
            </div>
        @endunless
    </section>
@endsection

@extends('layouts.admin', ['title' => 'Visitation', 'active' => 'visits'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Visitation</h1>
            <span class="page-head__sub">Plan visits to members and visitors, choose who goes, and write a short report afterwards. {{ $thisMonth }} {{ \Illuminate\Support\Str::plural('visit', $thisMonth) }} made this month.</span>
        </div>
        <div class="actions"><a href="#new-visit" class="btn btn--gold"><x-icon name="plus" :size="16" />Plan a visit</a></div>
    </header>

    <nav class="tabs" aria-label="Visits">
        @foreach ($tabs as $key => $t)
            <a href="{{ route('admin.visits.index', ['tab' => $key]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $t['label'] }}@if ($t['count'] !== null) <span>{{ $t['count'] }}</span>@endif</a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($visits as $v)
            <article @class(['prayer-card', 'prayer-card--unread' => $v->isLate()])>
                <div class="prayer-card__head">
                    @if ($v->member)
                        <a href="{{ route('admin.members.show', $v->member) }}" class="prayer-card__who">{{ $v->name }}</a>
                    @else
                        <span class="prayer-card__who">{{ $v->name }}</span>
                    @endif
                    <span class="pill pill--info">{{ $v->purposeLabel() }}</span>
                    @if ($v->status === 'done')
                        <span class="pill pill--ok">{{ \App\Models\Visit::OUTCOMES[$v->outcome] ?? 'Done' }}</span>
                    @elseif ($v->status === 'cancelled')
                        <span class="pill pill--draft">Cancelled</span>
                    @elseif ($v->isLate())
                        <span class="pill pill--warn">Report due</span>
                    @endif
                    @if ($v->followUp)<span class="pill pill--draft">For a follow-up</span>@endif
                    <span class="prayer-card__when">{{ $v->scheduled_at->format('D j M, g:i A') }}</span>
                </div>
                <span class="prayer-card__contact">
                    <strong>Going:</strong> {{ $v->visitors->pluck('name')->join(', ') ?: 'Nobody yet' }}
                    @if ($v->address) · {{ $v->address }}@endif
                    @if ($v->phone) · {{ $v->phone }}@endif
                    @if ($v->tell_them) · they've been told @endif
                </span>
                @if ($v->notes)<p class="prayer-card__text">{{ $v->notes }}</p>@endif
                @if ($v->status === 'done' && $v->report)
                    <p class="prayer-card__text"><strong>{{ $v->doer?->name ?? 'Report' }}:</strong> {{ $v->report }}</p>
                @endif
                @if ($v->status === 'planned')
                    <div class="list-item__actions" style="flex-wrap: wrap; align-items: flex-start">
                        @if ($v->phone)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $v->phone) }}" class="btn btn--ghost btn--xs">Call</a>
                        @endif
                        @if ($v->address)
                            <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($v->address) }}" class="btn btn--ghost btn--xs" target="_blank" rel="noopener">Directions</a>
                        @endif
                        <details class="training-edit" style="margin: 0">
                            <summary class="btn btn--navy btn--xs">How it went</summary>
                            <form method="POST" action="{{ route('admin.visits.complete', $v) }}" class="form" style="gap: 8px; margin-top: 8px; min-width: min(420px, 80vw)">
                                @csrf
                                <label class="field">Outcome
                                    <select name="outcome" class="select-sm">
                                        @foreach ($outcomes as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field">Report <textarea name="report" rows="3" maxlength="2000" placeholder="How they are, what you prayed about, anything the pastors should know or do next"></textarea></label>
                                <button type="submit" class="btn btn--navy btn--xs" style="align-self: flex-start">Save report</button>
                            </form>
                        </details>
                        <details class="training-edit" style="margin: 0">
                            <summary class="btn btn--ghost btn--xs">Change</summary>
                            <form method="POST" action="{{ route('admin.visits.update', $v) }}" class="form" style="gap: 8px; margin-top: 8px; min-width: min(420px, 80vw)">
                                @csrf
                                @method('PUT')
                                <label class="field">When <input type="datetime-local" name="scheduled_at" value="{{ $v->scheduled_at->format('Y-m-d\TH:i') }}" required></label>
                                <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                                    <legend>Who's going</legend>
                                    @foreach ($carers as $c)
                                        <label class="login__remember"><input type="checkbox" name="visitors[]" value="{{ $c->id }}" @checked($v->visitors->contains($c))>{{ $c->name }}</label>
                                    @endforeach
                                </fieldset>
                                <button type="submit" class="btn btn--navy btn--xs" style="align-self: flex-start">Save</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('admin.visits.cancel', $v) }}" data-confirm="Cancel the visit to {{ $v->name }}?">
                            @csrf
                            <button type="submit" class="btn btn--ghost btn--xs">Cancel visit</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <p class="empty-state">{{ $tab === 'mine' ? 'No visits planned for you.' : 'Nothing here yet.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $visits])
    </section>

    <section class="box" id="new-visit">
        <h2>Plan a visit</h2>
        @if ($followUp)
            <p class="box__note">For the follow-up with <strong>{{ $followUp->personName() }}</strong> ({{ $followUp->reasonLabel() }}). Recording the visit as made closes the follow-up.</p>
        @endif
        <form method="POST" action="{{ route('admin.visits.store') }}" class="form">
            @csrf
            @if ($followUp)<input type="hidden" name="follow_up_id" value="{{ $followUp->id }}">@endif
            <div class="form-row">
                <label class="field">Member
                    <select name="member_id">
                        <option value="">{{ $followUp && ! $followUp->member ? $followUp->personName().' (visitor)' : 'Someone not on the register…' }}</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}" @selected((int) old('member_id', $followUp?->member_id) === $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </label>
                @unless ($followUp)
                    <label class="field">Or their name <input type="text" name="name" value="{{ old('name') }}" maxlength="100" placeholder="For a visitor or someone not on the register"></label>
                @endunless
                <label class="field">Phone (optional) <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" placeholder="Taken from their record if empty"></label>
            </div>
            @error('name') <span class="field-error">{{ $message }}</span> @enderror
            <div class="form-row">
                <label class="field">Why
                    <select name="purpose">
                        @foreach ($purposes as $key => $label)
                            <option value="{{ $key }}" @selected(old('purpose', $followUp && isset($purposes[$followUp->reason]) ? $followUp->reason : 'pastoral') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">When
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', now()->addDays(2)->setTime(17, 0)->format('Y-m-d\TH:i')) }}" required>
                    @error('scheduled_at') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <label class="field">Address (optional) <input type="text" name="address" value="{{ old('address') }}" maxlength="255" placeholder="Taken from their record if empty; a hospital ward, for example"></label>
            <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                <legend>Who's going <x-tip text="Everyone chosen is emailed now and reminded the day before. Choose two people where you can." /></legend>
                <div style="display: flex; flex-wrap: wrap; gap: 4px 18px">
                    @foreach ($carers as $c)
                        <label class="login__remember"><input type="checkbox" name="visitors[]" value="{{ $c->id }}" @checked(in_array($c->id, array_map('intval', old('visitors', [auth()->id()])), true))>{{ $c->is(auth()->user()) ? 'Me' : $c->name }}</label>
                    @endforeach
                </div>
                @error('visitors') <span class="field-error">{{ $message }}</span> @enderror
            </fieldset>
            <label class="field">Notes for whoever's going (optional) <textarea name="notes" rows="2" maxlength="1000">{{ old('notes', $followUp?->note) }}</textarea></label>
            <label class="login__remember"><input type="checkbox" name="tell_them" value="1" @checked(old('tell_them'))>Let them know we're coming (email, and WhatsApp if it's on), now and the day before</label>
            <div><button type="submit" class="btn btn--navy">Plan the visit</button></div>
        </form>
    </section>
@endsection

@extends('layouts.unit', ['title' => $case->reference, 'active' => 'welfare'])

@php($naira = fn (?int $kobo) => '₦'.number_format(($kobo ?? 0) / 100))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('unit.welfare.index', $unit) }}" class="box__link">← Welfare cases</a>
            <h1>{{ $case->personName() }}</h1>
            <span class="page-head__sub">{{ $case->reference }} · {{ $case->categoryLabel() }} · opened {{ $case->created_at->format('j M Y') }}{{ $case->source === 'member' ? ' by the member' : '' }}</span>
        </div>
        <div class="actions">
            @if ($case->urgency === 'urgent')<span class="pill pill--warn">Urgent</span>@endif
            <span class="pill pill--info">{{ $case->statusLabel() }}</span>
        </div>
    </header>

    @error('status') <p class="flash flash--error" role="alert">{{ $message }}</p> @enderror

    <div class="row">
        <section class="box grow">
            <h2>{{ $case->summary }}</h2>
            @if ($case->details)<p class="box__note" style="white-space: pre-line; margin: 0; color: var(--ink)">{{ $case->details }}</p>@endif
            <dl class="detail-list">
                <dt>Phone</dt><dd>@if ($case->phone())<a href="tel:{{ preg_replace('/[^\d+]/', '', $case->phone()) }}">{{ $case->phone() }}</a>@else — @endif</dd>
                @if ($case->member)
                    <dt>Unit</dt><dd>{{ $case->member->unit?->name ?? '—' }}</dd>
                    <dt>Fellowship</dt><dd>{{ $case->member->fellowship?->name ?? '—' }}</dd>
                @endif
                <dt>Requested</dt><dd>{{ $case->amount_requested ? $naira($case->amount_requested) : 'No money asked for' }}</dd>
                <dt>Approved</dt><dd>{{ $case->amount_approved !== null ? $naira($case->amount_approved) : '—' }}@if ($case->decider) by {{ $case->decider->name }}@endif</dd>
                <dt>Given</dt><dd>{{ $naira($paid) }}</dd>
                @if ($case->decision_note)<dt>Decision note</dt><dd>{{ $case->decision_note }}</dd>@endif
            </dl>
            @if ($history->isNotEmpty())
                <p class="box__note" style="margin: 0">Earlier cases: @foreach ($history as $h)<a href="{{ route('unit.welfare.show', [$unit, $h]) }}">{{ $h->reference }}</a> ({{ $h->categoryLabel() }}, {{ $h->statusLabel() }})@if (! $loop->last), @endif @endforeach</p>
            @endif
        </section>

        <section class="box side-col">
            <h2>Decision</h2>
            @if (in_array($case->status, ['new', 'in_review', 'awaiting_pastor']))
                @if ($case->status === 'awaiting_pastor' && ! $isPastor)
                    <p class="box__note" style="margin: 0">You recommended {{ $naira($case->amount_approved) }}. A pastor needs to approve it because it's above ₦{{ number_format($limit) }}.</p>
                @else
                    @if ($case->status === 'new')
                        <form method="POST" action="{{ route('unit.welfare.act', [$unit, $case]) }}">
                            @csrf
                            <input type="hidden" name="action" value="review">
                            <button type="submit" class="btn btn--ghost btn--xs">I'm looking into it</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('unit.welfare.act', [$unit, $case]) }}" class="form">
                        @csrf
                        <input type="hidden" name="action" value="approve">
                        <label class="field">Amount to give (₦, 0 for support without money)
                            <input type="number" name="amount" value="{{ old('amount', $case->amount_approved !== null ? $case->amount_approved / 100 : ($case->amount_requested ? $case->amount_requested / 100 : 0)) }}" min="0" step="1">
                        </label>
                        <label class="field">Note (optional) <input type="text" name="note" maxlength="255"></label>
                        <button type="submit" class="btn btn--navy">{{ $isPastor ? 'Approve' : 'Approve (or send to a pastor if above ₦'.number_format($limit).')' }}</button>
                    </form>
                    <form method="POST" action="{{ route('unit.welfare.act', [$unit, $case]) }}" class="form">
                        @csrf
                        <input type="hidden" name="action" value="decline">
                        <label class="field">Reason for declining <input type="text" name="reason" required maxlength="255"></label>
                        @error('reason') <span class="field-error">{{ $message }}</span> @enderror
                        <button type="submit" class="btn btn--danger btn--xs">Decline</button>
                    </form>
                @endif
            @elseif (in_array($case->status, ['approved', 'supported']))
                @if ($case->amount_approved > $paid)
                    <form method="POST" action="{{ route('unit.welfare.pay', [$unit, $case]) }}" class="form">
                        @csrf
                        <label class="field">Amount given (₦)
                            <input type="number" name="amount" value="{{ ($case->amount_approved - $paid) / 100 }}" min="1" step="1" required>
                            @error('amount') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">How
                            <select name="method">
                                @foreach (config('welfare.payment_methods') as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">Date <input type="date" name="paid_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
                        <label class="field">Reference (optional) <input type="text" name="reference" maxlength="255" placeholder="e.g. transfer ID, receipt number"></label>
                        <button type="submit" class="btn btn--navy">Record payment</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('unit.welfare.act', [$unit, $case]) }}" class="form">
                    @csrf
                    <input type="hidden" name="action" value="close">
                    <label class="field">Closing note (optional) <input type="text" name="note" maxlength="255"></label>
                    <button type="submit" class="btn btn--ghost btn--xs">Close the case</button>
                </form>
            @else
                <form method="POST" action="{{ route('unit.welfare.act', [$unit, $case]) }}">
                    @csrf
                    <input type="hidden" name="action" value="reopen">
                    <button type="submit" class="btn btn--ghost btn--xs">Reopen</button>
                </form>
            @endif

            <h2 style="margin-top: 8px">Case worker &amp; check-ins</h2>
            <form method="POST" action="{{ route('unit.welfare.update', [$unit, $case]) }}" class="form">
                @csrf
                @method('PUT')
                <label class="field">Case worker
                    <select name="assigned_member_id">
                        <option value="">Not assigned</option>
                        @foreach ($workers as $w)
                            <option value="{{ $w->id }}" @selected($case->assigned_member_id === $w->id)>{{ $w->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_member_id') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Next check-in <input type="date" name="next_check_on" value="{{ $case->next_check_on?->toDateString() }}"></label>
                <label class="field">Urgency
                    <select name="urgency">
                        <option value="normal" @selected($case->urgency === 'normal')>Normal</option>
                        <option value="urgent" @selected($case->urgency === 'urgent')>Urgent</option>
                    </select>
                </label>
                <button type="submit" class="btn btn--ghost btn--xs">Save</button>
            </form>
        </section>
    </div>

    @if ($case->payments->isNotEmpty())
        <section class="box box--flush">
            <div class="box__head"><h2>Payments</h2><span class="box__note">Each is also in the church's expenses as "Welfare support, case {{ $case->reference }}"</span></div>
            @foreach ($case->payments as $p)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $naira($p->amount) }} · {{ config("welfare.payment_methods.{$p->method}") }}</span>
                        <span class="list-item__meta">{{ $p->paid_on->format('j M Y') }}{{ $p->reference ? ' · '.$p->reference : '' }} · recorded by {{ $p->recorder?->name ?? 'someone' }}</span>
                    </span>
                </div>
            @endforeach
        </section>
    @endif

    <section class="box box--flush">
        <div class="box__head"><h2>Case log</h2></div>
        <form method="POST" action="{{ route('unit.welfare.note', [$unit, $case]) }}" class="form" style="padding: 0 20px 16px">
            @csrf
            <div class="form-row">
                <label class="field">What
                    <select name="kind">
                        @foreach (config('welfare.note_kinds') as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">When <input type="date" name="happened_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
                <label class="field">Next check-in (optional) <input type="date" name="next_check_on" min="{{ today()->toDateString() }}"></label>
            </div>
            <label class="field">Notes <textarea name="body" required maxlength="5000" style="height: 80px" placeholder="e.g. Visited at home with Sis. Ada; brought foodstuff; surgery is on Monday">{{ old('body') }}</textarea>@error('body') <span class="field-error">{{ $message }}</span> @enderror</label>
            <div><button type="submit" class="btn btn--navy btn--xs">Add to log</button></div>
        </form>
        @foreach ($case->notes->sortByDesc('id') as $n)
            <article class="prayer-card">
                <div class="prayer-card__head">
                    <span class="pill {{ $n->kind === 'status' ? 'pill--draft' : 'pill--info' }}">{{ $n->kind === 'status' ? 'Update' : config("welfare.note_kinds.{$n->kind}") }}</span>
                    <span class="prayer-card__when">{{ $n->happened_on->format('j M Y') }} · {{ $n->author?->name ?? 'System' }}</span>
                </div>
                <p class="prayer-card__text" style="white-space: pre-line">{{ $n->body }}</p>
            </article>
        @endforeach
    </section>
@endsection

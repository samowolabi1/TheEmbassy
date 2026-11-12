@extends('layouts.app', ['title' => 'Welfare', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
        <span class="eyebrow">WELFARE</span>
        <h1 class="display notices-hero__title">We're here for you.</h1>
        <p class="lede" style="max-width: 680px">Going through something? Let the welfare team know. Your request is confidential: only the welfare team and pastors see it.</p>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif

        @if ($member->status === 'active' && $packages->isNotEmpty())
            @foreach ($packages as $package)
                @php $mine = $applications->firstWhere('welfare_package_id', $package->id); @endphp
                <div class="card notice-form__main package-card" style="flex: none">
                    <span class="eyebrow">WELFARE PACKAGE · APPLY BY {{ strtoupper($package->closes_at->format('j M')) }}</span>
                    <h2 class="h2 h2--sm" style="margin: 0">{{ $package->title }}</h2>
                    @if ($package->description)
                        <p class="lede" style="margin: 0">{!! nl2br(e($package->description)) !!}</p>
                    @endif
                    @if ($package->items)
                        <p style="margin: 0"><strong>In the package:</strong> {{ $package->items }}</p>
                    @endif
                    @if ($mine && $mine->status !== 'withdrawn')
                        <span class="status-chip {{ in_array($mine->status, ['approved', 'collected']) ? 'status-chip--ok' : '' }}">{{ $mine->statusLabel() }}</span>
                    @elseif ($package->placesLeft() === 0)
                        <p class="lede" style="margin: 0">All places have been given out. You can still apply to join the waiting list.</p>
                    @endif
                    @if (! $mine || $mine->status === 'withdrawn')
                        <form method="POST" action="{{ route('account.welfare.apply', $package) }}" class="notice-form__group">
                            @csrf
                            <label class="field">How many people in your household?
                                <input type="number" name="household_size" min="1" max="30" required value="{{ old('household_size', 1) }}" style="max-width: 160px">
                                @error('household_size') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Anything the welfare team should know? (optional)
                                <textarea name="note" maxlength="1000" rows="3">{{ old('note') }}</textarea>
                            </label>
                            <button type="submit" class="btn btn--gold" style="align-self: flex-start">Apply</button>
                            <span class="field-hint">Only the welfare team and pastors see your application.</span>
                        </form>
                    @endif
                </div>
            @endforeach
        @endif

        @if ($applications->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Your package applications</h2>
                @foreach ($applications as $a)
                    <div class="package-row">
                        <span class="stack" style="gap: 2px">
                            <strong>{{ $a->package->title }}</strong>
                            <span class="field-hint">{{ $a->statusLabel() }}@if ($a->decision_note) · "{{ $a->decision_note }}"@endif</span>
                            @if ($a->status === 'approved')
                                <span class="package-ref">Show this when you collect: <strong>{{ $a->reference }}</strong></span>
                                @if ($a->package->collection_details) <span class="field-hint">{{ $a->package->collection_details }}</span> @endif
                            @endif
                        </span>
                        @if (in_array($a->status, ['pending', 'waitlist', 'approved']))
                            <form method="POST" action="{{ route('account.welfare.withdraw', $a) }}" onsubmit="return confirm('Withdraw your application?')">
                                @csrf
                                <button type="submit" class="btn btn--outline btn--sm">Withdraw</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($assigned->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Cases you're helping with</h2>
                <ul class="mini-list">
                    @foreach ($assigned as $c)
                        <li><a href="{{ route('account.welfare.case', $c) }}" style="font-weight: 700">{{ $c->personName() }} · {{ $c->summary }}</a><span>{{ $c->reference }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($requests->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Your requests</h2>
                <ul class="mini-list">
                    @foreach ($requests as $r)
                        <li><span>{{ $r->summary }} · {{ $r->created_at->format('j M Y') }}</span><strong>{{ $r->requesterStatus() }}</strong></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($member->status === 'active')
            <form method="POST" action="{{ route('account.welfare.store') }}" class="card notice-form__main" style="flex: none">
                @csrf
                <fieldset class="notice-form__group">
                    <legend>Ask for help with something else</legend>
                    @include('unit.welfare.fields')
                    <button type="submit" class="btn btn--gold" style="align-self: flex-start">Send to the welfare team</button>
                </fieldset>
            </form>
        @else
            <p class="lede">Once your membership is confirmed you can make a request here. If it's urgent, please speak to a pastor.</p>
        @endif
    </section>
@endsection

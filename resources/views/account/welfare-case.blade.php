@extends('layouts.app', ['title' => 'Welfare case', 'active' => null])

@section('content')
    <section class="container section--tight section--last stack" style="gap: 20px">
        <a href="{{ route('account.welfare') }}" class="back-link"><x-icon name="chevron-left" :size="16" />Welfare</a>
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif
        <div class="card notice-form__main" style="flex: none">
            <span class="eyebrow">{{ $case->reference }} · CONFIDENTIAL</span>
            <h1 class="h2" style="margin: 0">{{ $case->personName() }}</h1>
            <p class="lede" style="margin: 0">{{ $case->summary }}</p>
            @if ($case->details)<p style="margin: 0; white-space: pre-line">{{ $case->details }}</p>@endif
            @if ($case->phone())<a href="tel:{{ preg_replace('/[^\d+]/', '', $case->phone()) }}" class="btn btn--navy btn--sm" style="align-self: flex-start">Call {{ $case->phone() }}</a>@endif
        </div>
        <form method="POST" action="{{ route('account.welfare.note', $case) }}" class="card notice-form__main" style="flex: none">
            @csrf
            <fieldset class="notice-form__group">
                <legend>Log a call or visit</legend>
                <div class="grid-auto" style="--min: 200px; --gap: 14px">
                    <label class="field">What
                        <select name="kind">
                            @foreach ($kinds as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">When <input type="date" name="happened_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
                </div>
                <label class="field">How it went <textarea name="body" required maxlength="5000" style="height: 100px"></textarea>@error('body') <span class="field-error">{{ $message }}</span> @enderror</label>
                <button type="submit" class="btn btn--gold" style="align-self: flex-start">Add to the case log</button>
            </fieldset>
        </form>
        <div class="card notice-form__main" style="flex: none">
            <h2 class="h2 h2--sm" style="margin: 0">Case log</h2>
            <ul class="mini-list">
                @foreach ($case->notes->sortByDesc('id') as $n)
                    <li style="flex-direction: column; gap: 4px"><strong>{{ $n->happened_on->format('j M') }} · {{ $n->author?->name ?? 'Update' }}</strong><span style="white-space: pre-line">{{ $n->body }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection

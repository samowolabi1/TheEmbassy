@extends('layouts.admin', ['title' => 'Record income & spending', 'active' => 'money'])

@php($naira = fn (int $kobo) => '₦'.number_format($kobo / 100, $kobo % 100 ? 2 : 0))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Record income &amp; spending</h1>
            <span class="page-head__sub">Write down money that came in or went out for the church, with a receipt if you have one. Finance or a pastor checks each entry.@if (auth()->user()->canManage('expenses')) <a href="{{ route('admin.income.index') }}" class="box__link">See the summary →</a>@endif</span>
        </div>
    </header>

    <div class="row">
        <section class="box grow">
            <nav class="tabs" aria-label="Money in or out" style="margin-bottom: 16px">
                <a href="{{ route('admin.money.index', ['type' => 'in']) }}" class="tab" @if ($type === 'in') aria-current="page" @endif>Money in</a>
                <a href="{{ route('admin.money.index', ['type' => 'out']) }}" class="tab" @if ($type === 'out') aria-current="page" @endif>Money out</a>
            </nav>

            @if ($type === 'in')
                <form method="POST" action="{{ route('admin.money.income.store') }}" enctype="multipart/form-data" class="form">
                    @csrf
                    <label class="field">What is it
                        <input type="text" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="e.g. Sunday offering, first service">
                        @error('title') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <div class="form-row">
                        <label class="field">Category
                            <select name="category" required>
                                @foreach ($incomeCategories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            @error('category') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Amount (₦)
                            <input type="number" name="amount" value="{{ old('amount') }}" required min="1" step="0.01" inputmode="decimal">
                            @error('amount') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <div class="form-row">
                        <label class="field">Date received
                            <input type="date" name="received_on" value="{{ old('received_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                            @error('received_on') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">How it came in
                            <select name="method" required>
                                @foreach (\App\Models\Income::METHODS as $key => $label)
                                    <option value="{{ $key }}" @selected(old('method', 'cash') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="field">Notes (optional)
                        <textarea name="description" maxlength="1000" style="height: 80px" placeholder="e.g. Counted by Bro. Segun and Sis. Kemi, banked Monday">{{ old('description') }}</textarea>
                    </label>
                    <label class="field">Receipt or count sheet (photo or PDF, optional, up to 5 MB)
                        <input type="file" name="receipt" accept="image/*,.pdf">
                        @error('receipt') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <div><button type="submit" class="btn btn--navy">Record money in</button></div>
                </form>
            @else
                <form method="POST" action="{{ route('admin.expenses.store') }}" enctype="multipart/form-data" class="form">
                    @csrf
                    <label class="field">What for
                        <input type="text" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="e.g. Generator diesel for Sunday">
                        @error('title') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <div class="form-row">
                        <label class="field">Category
                            <select name="category" required>
                                @foreach ($expenseCategories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            @error('category') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Amount (₦)
                            <input type="number" name="amount" value="{{ old('amount') }}" required min="1" step="0.01" inputmode="decimal">
                            @error('amount') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Date spent
                            <input type="date" name="spent_on" value="{{ old('spent_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                            @error('spent_on') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <label class="field">Notes (optional)
                        <textarea name="description" maxlength="1000" style="height: 80px">{{ old('description') }}</textarea>
                    </label>
                    <label class="field">Receipt (photo or PDF, up to 5 MB)
                        <input type="file" name="receipt" accept="image/*,.pdf">
                        @error('receipt') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <div><button type="submit" class="btn btn--navy">Record money out</button></div>
                </form>
            @endif
        </section>

        <section class="box side-col box--flush">
            <div class="box__head"><h2>What you've recorded</h2></div>
            @forelse ($mine as $row)
                @php($item = $row['item'])
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $row['kind'] === 'in' ? '+' : '−' }}{{ $naira($item->amount) }} · {{ $item->title }}</span>
                        <span class="list-item__meta">{{ $row['kind'] === 'in' ? 'In' : 'Out' }} · {{ $item->category }} · {{ $row['date']->format('j M') }}@if ($item->decline_reason) · "{{ $item->decline_reason }}"@endif</span>
                    </span>
                    @php($status = $item->status)
                    <span @class(['pill', 'pill--ok' => in_array($status, ['confirmed', 'approved'], true), 'pill--warn' => $status === 'declined', 'pill--info' => $status === 'pending'])>{{ match ($status) { 'pending' => 'Being checked', 'confirmed' => 'Confirmed', 'approved' => 'Approved', default => 'Declined' } }}</span>
                </div>
            @empty
                <p class="empty-state">Nothing yet. Entries you record show here with where they're up to.</p>
            @endforelse
        </section>
    </div>
@endsection

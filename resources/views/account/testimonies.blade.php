@extends('layouts.app', ['title' => 'Share a testimony', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
        <span class="eyebrow">TESTIMONY</span>
        <h1 class="display notices-hero__title">What has God done?</h1>
        <p class="lede" style="max-width: 680px">"They overcame him by the blood of the Lamb, and by the word of their testimony." Share your story to encourage the church. A pastor reads every testimony before it's published.</p>
        <a href="{{ route('testimonies.index') }}" class="accent-bronze" style="font-weight: 700">Read other testimonies</a>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('account.testimonies.store') }}" class="card notice-form__main" style="flex: none">
            @csrf
            <fieldset class="notice-form__group">
                <legend>Share your testimony</legend>
                <div class="form-row">
                    <label class="field">A title
                        <input type="text" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="e.g. God healed my mother">
                        @error('title') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">What kind of testimony
                        <select name="category" required>
                            @foreach (\App\Models\Testimony::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="field">Your story
                    <textarea name="body" required minlength="40" maxlength="5000" rows="9" placeholder="What happened, what you prayed for, and what God did.">{{ old('body') }}</textarea>
                    @error('body') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <fieldset class="notice-form__group" style="gap: 8px">
                    <legend>Show it with</legend>
                    <div class="pill-choices">
                        @foreach (\App\Models\Testimony::NAME_DISPLAYS as $key => $label)
                            <label class="pill-choice"><input type="radio" name="name_display" value="{{ $key }}" @checked(old('name_display', 'full') === $key)><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
                <label class="check"><input type="checkbox" name="share_publicly" value="1" @checked(old('share_publicly', true))>Put it on the church website for everyone to read</label>
                <label class="check"><input type="checkbox" name="share_in_church" value="1" @checked(old('share_in_church', true))>It may be read out in a service</label>
                <button type="submit" class="btn btn--gold" style="align-self: flex-start">Share my testimony</button>
            </fieldset>
        </form>

        @if ($mine->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Your testimonies</h2>
                @foreach ($mine as $t)
                    <div class="package-row">
                        <span class="stack" style="gap: 2px">
                            <strong>{{ $t->title }}</strong>
                            <span class="field-hint">{{ $t->categoryLabel() }} · shared {{ $t->created_at->format('j M Y') }}{{ $t->praises ? ' · 🙌 '.$t->praises : '' }}</span>
                            @if ($t->status === 'declined' && $t->review_note)
                                <span class="field-hint">"{{ $t->review_note }}"</span>
                            @endif
                        </span>
                        <span style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <span class="status-chip {{ $t->status === 'published' ? 'status-chip--ok' : '' }}">{{ $t->statusLabel() }}</span>
                            @if ($t->status === 'published' && $t->share_publicly)
                                <a href="{{ route('testimonies.show', $t) }}" class="btn btn--outline btn--sm">View</a>
                            @endif
                            @if (in_array($t->status, ['pending', 'published']))
                                <form method="POST" action="{{ route('account.testimonies.withdraw', $t) }}" onsubmit="return confirm('Withdraw this testimony?')">
                                    @csrf
                                    <button type="submit" class="btn btn--outline btn--sm">Withdraw</button>
                                </form>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection

@extends('layouts.app', [
    'title' => $programme->title,
    'description' => $programme->tagline ?: \Illuminate\Support\Str::limit((string) $programme->intro, 150),
    'image' => $programme->bannerUrl(),
])

@section('content')
    @php
        $open = $programme->isOpen();
        $choiceWords = ['First choice', 'Second choice (optional)', 'Third choice (optional)'];
    @endphp
    <section class="container empower-top">
        @if ($preview)
            <p class="contact-note" role="status">Preview: this programme isn't published, so only staff can see this page.</p>
        @endif
        @if ($programme->bannerUrl())
            <div class="empower-hero" style="background-image: url('{{ $programme->bannerUrl() }}')" role="img" aria-label="{{ $programme->title }}"></div>
        @else
            <div class="empower-hero empower-hero--default" aria-hidden="true"><span>{{ $programme->title }}</span></div>
        @endif
        <div class="empower-intro">
            <div class="stack" style="gap: 10px; flex: 1 1 420px; min-width: 0">
                <span class="eyebrow">YOUTH EMPOWERMENT</span>
                <h1 class="display empower-title">{{ $programme->title }}</h1>
                @if ($programme->tagline)<p class="lede" style="margin: 0">{{ $programme->tagline }}</p>@endif
                <div class="empower-facts">
                    <span @class(['empower-chip', 'empower-chip--open' => $open])>
                        @if ($open)
                            Applications open{{ $programme->closes_on ? ' until '.$programme->closes_on->format('j F') : '' }}
                        @elseif ($programme->opens_on && $programme->opens_on->isFuture())
                            Applications open {{ $programme->opens_on->format('j F Y') }}
                        @else
                            Applications are closed
                        @endif
                    </span>
                    @if ($programme->ageRule())<span class="empower-chip">{{ $programme->ageRule() }}</span>@endif
                    <span class="empower-chip">{{ $tracks->count() }} {{ \Illuminate\Support\Str::plural('option', $tracks->count()) }} to choose from</span>
                </div>
            </div>
            @if ($open)<a href="#apply" class="btn btn--gold btn--lg empower-cta">Apply now</a>@endif
        </div>
    </section>

    @if ($programme->intro)
        <section class="container section--tight">
            <div class="empower-about">{{ $programme->intro }}</div>
        </section>
    @endif

    @if ($tracks->isNotEmpty())
        <section class="container section--tight" id="choices">
            <h2 class="h2 h2--sm">What you can choose</h2>
            <div class="empower-tracks">
                @foreach ($tracks as $t)
                    @php
                        $left = $t->placesLeft();
                    @endphp
                    <article class="empower-track">
                        <h3>{{ $t->name }}</h3>
                        @if ($t->description)<p>{{ $t->description }}</p>@endif
                        @if ($left !== null)
                            <span @class(['empower-places', 'is-full' => $left === 0])>{{ $left === 0 ? 'Full: you can still join the waiting list' : $left.' '.\Illuminate\Support\Str::plural('place', $left).' left' }}</span>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="container section--tight section--last" id="apply">
        @if ($applied)
            <div class="card empower-done" role="status">
                <span class="empower-done__tick" aria-hidden="true">✓</span>
                <h2>Thank you, {{ \App\Models\Member::firstNameOf($applied->name) }}!</h2>
                <p>Your application is in. Your reference is <strong>{{ $applied->reference }}</strong>{{ $applied->email ? ', and we\'ve emailed you a copy' : '' }}.</p>
                @if ($applied->choices->isNotEmpty())<p>You chose: {{ $applied->choices->pluck('name')->join(', ', ' and ') }}.</p>@endif
                @if ($programme->thank_you)<p>{{ $programme->thank_you }}</p>@endif
            </div>
        @elseif (! $open)
            <div class="card empower-done">
                <h2>{{ $programme->opens_on && $programme->opens_on->isFuture() ? 'Applications open on '.$programme->opens_on->format('l j F') : 'Applications are closed' }}</h2>
                <p>{{ $programme->opens_on && $programme->opens_on->isFuture() ? 'Come back then to apply.' : 'Thank you for your interest. Follow us to hear about the next programme.' }}</p>
            </div>
        @else
            <h2 class="h2 h2--sm">Apply</h2>
            <form method="POST" action="{{ route('empowerment.apply', $programme) }}" class="notice-form empower-form" novalidate>
                @csrf
                <label class="honeypot" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                <div class="card notice-form__main">
                    <fieldset class="notice-form__group">
                        <legend>About you</legend>
                        <div class="grid-auto" style="--min: 220px; --gap: 14px">
                            <label class="field">Full name
                                <input type="text" name="name" value="{{ old('name', $member?->name) }}" required maxlength="100" autocomplete="name" @error('name') aria-invalid="true" @enderror>
                                @error('name') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Phone (WhatsApp if you have it)
                                <input type="tel" name="phone" value="{{ old('phone', $member?->phone) }}" required maxlength="40" autocomplete="tel" @error('phone') aria-invalid="true" @enderror>
                                @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Email (optional)
                                <input type="email" name="email" value="{{ old('email', $member?->email ?? auth()->user()?->email) }}" maxlength="255" autocomplete="email">
                                <span class="field-hint">We'll send your confirmation and any news here.</span>
                                @error('email') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Date of birth{{ $programme->min_age || $programme->max_age ? '' : ' (optional)' }}
                                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $member?->birthdayYearKnown() ? $member->birthday->toDateString() : null) }}" max="{{ today()->subDay()->toDateString() }}" @if ($programme->min_age || $programme->max_age) required @endif @error('date_of_birth') aria-invalid="true" @enderror>
                                @error('date_of_birth') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Where you live (area)
                                <input type="text" name="location" value="{{ old('location', $member?->address) }}" maxlength="150" placeholder="e.g. Ikeja, Lagos">
                            </label>
                        </div>
                        <div class="pill-choices" role="radiogroup" aria-label="Gender">
                            @foreach (['female' => 'Female', 'male' => 'Male'] as $value => $label)
                                <label class="pill-choice"><input type="radio" name="gender" value="{{ $value }}" @checked(old('gender', $member?->gender) === $value)><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="notice-form__group">
                        <legend>{{ $programme->max_choices > 1 ? 'Your choices' : 'Your choice' }}</legend>
                        @if ($programme->max_choices > 1)<p class="field-hint" style="margin: 0">Put the one you want most first. We'll try to give you your first choice.</p>@endif
                        <div class="grid-auto" style="--min: 220px; --gap: 14px">
                            @for ($i = 0; $i < $programme->max_choices; $i++)
                                <label class="field">{{ $programme->max_choices > 1 ? $choiceWords[$i] : 'What would you like to do?' }}
                                    <select name="choices[{{ $i }}]" @if ($i === 0) required @endif @error('choices') aria-invalid="true" @enderror>
                                        <option value="">Choose…</option>
                                        @foreach ($tracks as $t)
                                            <option value="{{ $t->id }}" @selected((int) old("choices.{$i}") === $t->id)>{{ $t->name }}{{ $t->placesLeft() === 0 ? ' (full: waiting list)' : '' }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endfor
                        </div>
                        @error('choices') <span class="field-error">{{ $message }}</span> @enderror
                        @error('choices.*') <span class="field-error">{{ $message }}</span> @enderror
                    </fieldset>

                    @if ($questions->isNotEmpty())
                        <fieldset class="notice-form__group">
                            <legend>A few questions</legend>
                            @foreach ($questions as $q)
                                @php
                                    $name = 'answers['.$q->key().']';
                                    $old = old('answers.'.$q->key());
                                    $err = 'answers.'.$q->key();
                                @endphp
                                @if (in_array($q->type, ['radio', 'checkboxes', 'yes_no'], true))
                                    <fieldset class="field empower-q">
                                        <legend>{{ $q->label }}@unless ($q->is_required) <span class="field-hint" style="display: inline">(optional)</span>@endunless</legend>
                                        @if ($q->help)<span class="field-hint">{{ $q->help }}</span>@endif
                                        <div class="pill-choices">
                                            @foreach ($q->type === 'yes_no' ? ['Yes', 'No'] : $q->optionList() as $option)
                                                @if ($q->type === 'checkboxes')
                                                    <label class="pill-choice"><input type="checkbox" name="{{ $name }}[]" value="{{ $option }}" @checked(in_array($option, (array) $old, true))><span>{{ $option }}</span></label>
                                                @else
                                                    <label class="pill-choice"><input type="radio" name="{{ $name }}" value="{{ $option }}" @checked($old === $option) @if ($q->is_required) required @endif><span>{{ $option }}</span></label>
                                                @endif
                                            @endforeach
                                        </div>
                                        @error($err) <span class="field-error">{{ $message }}</span> @enderror
                                    </fieldset>
                                @else
                                    <label class="field empower-q">{{ $q->label }}@unless ($q->is_required) <span class="field-hint" style="display: inline">(optional)</span>@endunless
                                        @if ($q->help)<span class="field-hint">{{ $q->help }}</span>@endif
                                        @switch($q->type)
                                            @case('textarea')
                                                <textarea name="{{ $name }}" rows="4" maxlength="3000" @if ($q->is_required) required @endif @error($err) aria-invalid="true" @enderror>{{ $old }}</textarea>
                                                @break
                                            @case('select')
                                                <select name="{{ $name }}" @if ($q->is_required) required @endif>
                                                    <option value="">Choose…</option>
                                                    @foreach ($q->optionList() as $option)<option value="{{ $option }}" @selected($old === $option)>{{ $option }}</option>@endforeach
                                                </select>
                                                @break
                                            @default
                                                <input type="{{ ['number' => 'number', 'date' => 'date'][$q->type] ?? 'text' }}" name="{{ $name }}" value="{{ $old }}" @if ($q->type === 'text') maxlength="255" @endif @if ($q->is_required) required @endif @error($err) aria-invalid="true" @enderror>
                                        @endswitch
                                        @error($err) <span class="field-error">{{ $message }}</span> @enderror
                                    </label>
                                @endif
                            @endforeach
                        </fieldset>
                    @endif

                    <label class="check"><input type="checkbox" name="agree" value="1" @checked(old('agree')) required>I agree that {{ config('church.short_name') }} can use these details to process my application and contact me about it.</label>
                    @error('agree') <span class="field-error">{{ $message }}</span> @enderror
                    <button type="submit" class="btn btn--gold btn--lg" style="align-self: flex-start">Send my application</button>
                </div>
            </form>
        @endif
    </section>
@endsection

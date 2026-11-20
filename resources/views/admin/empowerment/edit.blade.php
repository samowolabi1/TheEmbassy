@extends('layouts.admin', ['title' => $programme->title.' · page and form', 'active' => 'empowerment'])

@section('content')
    @include('admin.empowerment._head', ['tab' => 'settings'])

    <form method="POST" action="{{ route('admin.empowerment.update', $programme) }}" enctype="multipart/form-data" class="box form" id="page">
        @csrf
        <h2>The page</h2>
        @if ($errors->settings->any())<p class="field-error" role="alert" style="margin: 0">{{ $errors->settings->first() }}</p>@endif
        <div class="empower-banner-preview">
            @if ($programme->bannerUrl())
                <img src="{{ $programme->bannerUrl() }}" alt="Current banner">
            @else
                <div class="empower-hero empower-hero--default" aria-hidden="true"><span>{{ $programme->title }}</span></div>
            @endif
        </div>
        <div class="form-row">
            <label class="field"><span>Banner <x-tip text="A wide picture across the top of the page. Change it any time; the old one is removed. Best at least 1600 × 600, with the important part in the middle." /></span>
                <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" style="height: auto; padding: 12px">
                <span class="field__hint">JPG, PNG or WebP, up to 8 MB. Without one, a designed banner shows.</span>
                @if ($programme->bannerUrl())<label class="login__remember"><input type="checkbox" name="remove_banner" value="1">Remove the banner</label>@endif
            </label>
            <label class="field">Name
                <input type="text" name="title" value="{{ old('title', $programme->title) }}" required maxlength="120">
            </label>
        </div>
        <label class="field">Tagline <input type="text" name="tagline" value="{{ old('tagline', $programme->tagline) }}" maxlength="200" placeholder="e.g. Learn a skill. Start something. Build your future."></label>
        <label class="field">About the programme
            <textarea name="intro" rows="6" maxlength="5000" placeholder="What it is, who it's for, how long it runs, what it costs (if anything), and what happens after they apply.">{{ old('intro', $programme->intro) }}</textarea>
        </label>
        <div class="form-row">
            <label class="field">Applications open (optional) <input type="date" name="opens_on" value="{{ old('opens_on', $programme->opens_on?->toDateString()) }}"></label>
            <label class="field">Applications close (optional) <input type="date" name="closes_on" value="{{ old('closes_on', $programme->closes_on?->toDateString()) }}"></label>
            <label class="field">Choices each person can pick
                <select name="max_choices">
                    @foreach ([1 => 'One', 2 => 'Up to two (first and second choice)', 3 => 'Up to three'] as $n => $label)
                        <option value="{{ $n }}" @selected((int) old('max_choices', $programme->max_choices) === $n)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="form-row">
            <label class="field">Youngest age (optional) <input type="number" name="min_age" value="{{ old('min_age', $programme->min_age) }}" min="5" max="99"></label>
            <label class="field">Oldest age (optional) <input type="number" name="max_age" value="{{ old('max_age', $programme->max_age) }}" min="5" max="99"></label>
        </div>
        <span class="field__hint">With an age range, applicants give their date of birth and anyone outside it is told kindly.</span>
        <label class="field"><span>After they apply (optional) <x-tip text="Added to the thank-you message on the page and in their confirmation email, e.g. when you'll be in touch or an orientation date." /></span>
            <textarea name="thank_you" rows="2" maxlength="600" placeholder="e.g. Shortlisted applicants will be called by 15 January for a short chat.">{{ old('thank_you', $programme->thank_you) }}</textarea>
        </label>
        <label class="switch-row"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $programme->is_published))> <strong>Published</strong> <span class="box__note" style="margin: 0">The page is on the website. People can apply between the dates above.</span></label>
        <div><button type="submit" class="btn btn--gold">Save the page</button></div>
    </form>

    <section class="box" id="choices">
        <h2>Choices people pick from <x-tip text="The empowerment options on offer, e.g. Catering and baking, Fashion design, Phone repair, Photography. Set places to limit how many you'll accept; the page shows how many are left." /></h2>
        @if ($errors->tracks->any())<p class="field-error" role="alert" style="margin: 0">{{ $errors->tracks->first() }}</p>@endif
        <form method="POST" action="{{ route('admin.empowerment.tracks', $programme) }}" class="stack" style="gap: 12px">
            @csrf
            @method('PUT')
            @foreach ($tracks as $t)
                <div class="builder-row">
                    <div class="builder-row__top">
                        <label class="field">Choice <input type="text" name="tracks[{{ $t->id }}][name]" value="{{ $t->name }}" maxlength="80" class="select-sm"></label>
                        <label class="field" style="flex: 0 1 120px">Places <input type="number" name="tracks[{{ $t->id }}][slots]" value="{{ $t->slots }}" min="1" placeholder="No limit" class="select-sm"></label>
                        <label class="field" style="flex: 0 1 90px">Order <input type="number" name="tracks[{{ $t->id }}][position]" value="{{ $t->position }}" min="0" class="select-sm"></label>
                    </div>
                    <label class="field">What they'll learn (optional) <input type="text" name="tracks[{{ $t->id }}][description]" value="{{ $t->description }}" maxlength="500" class="select-sm"></label>
                    <div class="builder-row__checks">
                        <label class="login__remember"><input type="checkbox" name="tracks[{{ $t->id }}][is_active]" value="1" @checked($t->is_active)>Offered</label>
                        <label class="login__remember"><input type="checkbox" name="tracks[{{ $t->id }}][remove]" value="1">Remove</label>
                        <span class="box__note" style="margin: 0">{{ $t->applicants_count }} picked · {{ $t->accepted_count }} accepted</span>
                    </div>
                </div>
            @endforeach
            <div class="builder-row" style="border-style: dashed">
                <div class="builder-row__top">
                    <label class="field">Add a choice <input type="text" name="new[name]" maxlength="80" placeholder="e.g. Catering and baking" class="select-sm"></label>
                    <label class="field" style="flex: 0 1 120px">Places <input type="number" name="new[slots]" min="1" placeholder="No limit" class="select-sm"></label>
                </div>
                <label class="field">What they'll learn (optional) <input type="text" name="new[description]" maxlength="500" placeholder="e.g. Cakes, small chops and running a food business" class="select-sm"></label>
            </div>
            <div><button type="submit" class="btn btn--navy btn--sm">Save choices</button></div>
        </form>
    </section>

    <section class="box" id="form">
        <h2>The application form <x-tip text="Everyone gives their name, phone, email (optional), gender, date of birth and where they live, and picks their choices. Add your own questions below." /></h2>
        <p class="box__note" style="margin: 0">Always asked: name, phone, email (optional), gender, date of birth{{ $programme->min_age || $programme->max_age ? ' (required, for the age range)' : '' }}, where they live, and their choice{{ $programme->max_choices > 1 ? 's' : '' }}. Your questions come after.</p>
        @if ($errors->questions->any())<p class="field-error" role="alert" style="margin: 0">{{ $errors->questions->first() }}</p>@endif
        <form method="POST" action="{{ route('admin.empowerment.questions', $programme) }}" class="stack" style="gap: 12px">
            @csrf
            @method('PUT')
            @foreach ($questions as $q)
                <div class="builder-row">
                    <div class="builder-row__top">
                        <label class="field">Question <input type="text" name="questions[{{ $q->id }}][label]" value="{{ $q->label }}" maxlength="200" class="select-sm"></label>
                        <label class="field" style="flex: 0 1 220px">Answer type
                            <select name="questions[{{ $q->id }}][type]" class="select-sm" data-question-type>
                                @foreach ($types as $key => $label)<option value="{{ $key }}" @selected($q->type === $key)>{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="field" style="flex: 0 1 90px">Order <input type="number" name="questions[{{ $q->id }}][position]" value="{{ $q->position }}" min="0" class="select-sm"></label>
                    </div>
                    <label class="field">Hint under the question (optional) <input type="text" name="questions[{{ $q->id }}][help]" value="{{ $q->help }}" maxlength="300" class="select-sm"></label>
                    <label class="field" data-question-options>Options, one per line <span class="box__note" style="display: inline; margin: 0">(for dropdowns, multiple choice and checkboxes)</span>
                        <textarea name="questions[{{ $q->id }}][options]" rows="{{ max(2, count($q->optionList())) }}" class="select-sm" style="height: auto">{{ implode("\n", $q->optionList()) }}</textarea>
                    </label>
                    <div class="builder-row__checks">
                        <label class="login__remember"><input type="checkbox" name="questions[{{ $q->id }}][is_required]" value="1" @checked($q->is_required)>Required</label>
                        <label class="login__remember"><input type="checkbox" name="questions[{{ $q->id }}][remove]" value="1">Remove</label>
                    </div>
                </div>
            @endforeach
            <div class="builder-row" style="border-style: dashed">
                <div class="builder-row__top">
                    <label class="field">Add a question <input type="text" name="new[label]" value="{{ old('new.label') }}" maxlength="200" placeholder="e.g. Which area of Lagos do you live in?" class="select-sm"></label>
                    <label class="field" style="flex: 0 1 220px">Answer type
                        <select name="new[type]" class="select-sm" data-question-type>
                            @foreach ($types as $key => $label)<option value="{{ $key }}" @selected(old('new.type') === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
                <label class="field">Hint (optional) <input type="text" name="new[help]" maxlength="300" class="select-sm"></label>
                <label class="field" data-question-options>Options, one per line (for dropdowns, multiple choice and checkboxes)
                    <textarea name="new[options]" rows="3" class="select-sm" style="height: auto">{{ old('new.options') }}</textarea>
                </label>
                <label class="login__remember"><input type="checkbox" name="new[is_required]" value="1">Required</label>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap"><button type="submit" class="btn btn--navy btn--sm">Save the form</button><a href="{{ route('empowerment.show', $programme) }}#apply" class="btn btn--ghost btn--sm" target="_blank" rel="noopener">See the form</a></div>
        </form>
    </section>

    @if ($programme->applications()->doesntExist())
        <section class="box">
            <h2>Delete this programme</h2>
            <form method="POST" action="{{ route('admin.empowerment.destroy', $programme) }}" data-confirm="Delete {{ $programme->title }}?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm">Delete programme</button>
            </form>
        </section>
    @endif
@endsection

@extends('layouts.unit', ['title' => 'Birthday cards', 'active' => 'birthdays'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Birthday cards</h1>
            <span class="page-head__sub">Every member gets a card from this template on their birthday, on their account{{ $community ? ' and in the community' : '' }}. Only the day is shown, never the year.</span>
        </div>
    </header>

    <div class="row">
        <section class="box" style="flex: 0 1 340px">
            <h2>Preview</h2>
            <img src="{{ route('unit.birthdays.preview', [$unit, 'v' => md5(json_encode($template))]) }}" alt="The birthday card template, with your name as the example" width="1080" height="1350" style="width: 100%; height: auto; border-radius: 16px; box-shadow: 0 10px 30px rgba(10, 29, 63, 0.18)">
            <p class="box__note" style="margin: 0">Shown with your own name{{ auth()->user()->member?->photo_path ? ' and photo' : '' }}. Cards are 1080 × 1350, the size for Instagram, Facebook and WhatsApp status.</p>
        </section>

        <section class="box grow">
            <h2>Template</h2>
            @if ($canLead)
                <form method="POST" action="{{ route('unit.birthdays.template', $unit) }}" enctype="multipart/form-data" class="form">
                    @csrf
                    @method('PUT')
                    <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                        <legend style="margin-bottom: 6px">Design</legend>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px 18px">
                            @foreach (\App\Services\Celebrations\BirthdayCard::DESIGNS as $key => $label)
                                <label class="login__remember"><input type="radio" name="design" value="{{ $key }}" @checked(old('design', $template['design']) === $key)>{{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="field">Headline
                        <input type="text" name="headline" value="{{ old('headline', $template['headline']) }}" required maxlength="30">
                        @error('headline') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Message
                        <textarea name="message" required maxlength="200" rows="3">{{ old('message', $template['message']) }}</textarea>
                        @error('message') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Verse or blessing (optional)
                        <textarea name="verse" maxlength="160" rows="2">{{ old('verse', $template['verse']) }}</textarea>
                        @error('verse') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Your own background picture (optional)
                        <input type="file" name="background" accept="image/jpeg,image/png,image/webp">
                        <span class="field__hint">Used instead of the design's colours, darkened so the words stand out. A portrait picture works best.</span>
                        @error('background') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    @if ($template['background'])
                        <label class="login__remember"><input type="checkbox" name="remove_background" value="1">Remove the background picture</label>
                    @endif
                    <label class="login__remember"><input type="checkbox" name="community" value="1" @checked($community)>Post each card in the community on the day</label>
                    <div><button type="submit" class="btn btn--gold">Save template</button></div>
                </form>
            @else
                <dl class="detail-list">
                    <dt>Design</dt><dd>{{ \App\Services\Celebrations\BirthdayCard::DESIGNS[$template['design']] }}</dd>
                    <dt>Headline</dt><dd>{{ $template['headline'] }}</dd>
                    <dt>Message</dt><dd>{{ $template['message'] }}</dd>
                    <dt>Verse</dt><dd>{{ $template['verse'] ?: 'None' }}</dd>
                </dl>
                <p class="box__note" style="margin: 0">Your HOD changes the template.</p>
            @endif
        </section>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Coming up (next {{ \App\Http\Controllers\Unit\BirthdayController::DAYS }} days)</h2></div>
        @forelse ($upcoming as $b)
            @php($m = $b['member'])
            <details class="list-item list-item--edit">
                <summary>
                    <span class="avatar" aria-hidden="true">{{ $b['date']->isToday() ? '🎂' : $b['date']->format('j') }}</span>
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $m->name }}</span>
                        <span class="list-item__meta">{{ $b['date']->isToday() ? 'Today' : $b['date']->format('l j M') }}{{ $m->unit ? ' · '.$m->unit->name : '' }}{{ $m->photo_path ? ' · has a photo' : ' · no photo yet' }}</span>
                    </span>
                    @unless ($m->celebrate_birthday)
                        <span class="pill pill--draft">Prefers not to be shared</span>
                    @endunless
                </summary>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-start">
                    <img src="{{ route('unit.birthdays.card', [$unit, $m]) }}" alt="Birthday card for {{ $m->name }}" width="180" height="225" loading="lazy" style="border-radius: 12px">
                    <div class="stack" style="gap: 12px; flex: 1 1 260px">
                        @unless ($m->celebrate_birthday)
                            <p class="box__note" style="margin: 0">{{ $m->name }} has asked not to be celebrated publicly. Their card isn't posted in the community; please don't share it on social media.</p>
                        @endunless
                        <div style="display: flex; gap: 8px; flex-wrap: wrap">
                            <a href="{{ route('unit.birthdays.card', [$unit, $m, 'download' => 1]) }}" class="btn btn--ghost btn--xs">Download card</a>
                            @if ($m->celebrate_birthday)
                                <form method="POST" action="{{ route('unit.birthdays.social', [$unit, $m]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn--ghost btn--xs">Make a social post</button>
                                </form>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('unit.birthdays.photo', [$unit, $m]) }}" enctype="multipart/form-data" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
                            @csrf
                            <label class="sr-only" for="photo-{{ $m->id }}">Photo for {{ $m->name }}'s card</label>
                            <input id="photo-{{ $m->id }}" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required class="select-sm">
                            <button type="submit" class="btn btn--navy btn--xs">{{ $m->photo_path ? 'Change photo' : 'Add photo' }}</button>
                        </form>
                    </div>
                </div>
            </details>
        @empty
            <p class="empty-state">No birthdays in the next {{ \App\Http\Controllers\Unit\BirthdayController::DAYS }} days, or members haven't added their birthday yet.</p>
        @endforelse
    </section>
@endsection

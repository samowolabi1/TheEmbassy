@extends('layouts.unit', ['title' => 'Birthday cards', 'active' => 'birthdays'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Birthday cards</h1>
            <span class="page-head__sub">Every member gets a card on their birthday, on their account{{ $community ? ' and in the community' : '' }}. Only the day is shown, never the year.</span>
        </div>
    </header>

    <form method="POST" action="{{ route('unit.birthdays.template', $unit) }}" class="stack" style="gap: 20px">
        @csrf
        @method('PUT')
        <section class="box">
            <div class="box__head" style="padding: 0; border: 0"><h2>Designs</h2></div>
            <p class="box__note" style="margin: 0">{{ $canLead ? 'Tick the designs to use. With more than one, each member gets one of them, and most years a different one. Shown with your name as the example.' : 'The designs in use, shown with your name as the example.' }}</p>
            @error('designs') <p class="field-error" style="margin: 0">{{ $message }}</p> @enderror
            <ul class="design-grid" role="list">
                @foreach ($designs as $key => $label)
                    @php($on = in_array($key, $enabled, true))
                    @continue(! $canLead && ! $on)
                    <li @class(['design-tile', 'design-tile--on' => $on])>
                        <img src="{{ route('unit.birthdays.preview', [$unit, 'design' => $key, 'v' => md5(json_encode($template).\App\Models\BirthdayDesign::stamp())]) }}" alt="" width="1080" height="1350" loading="lazy">
                        @if ($canLead)
                            <label class="login__remember"><input type="checkbox" name="designs[]" value="{{ $key }}" @checked($on)>{{ $label }}</label>
                        @else
                            <span class="design-tile__label">{{ $label }}</span>
                        @endif
                        @if (str_starts_with($key, 'upload-') && $canLead)
                            @php($upload = $uploads->firstWhere('id', (int) substr($key, 7)))
                            <span style="display: flex; gap: 6px; flex-wrap: wrap">
                                <a href="{{ route('unit.birthdays.designs.edit', [$unit, $upload]) }}" class="btn btn--ghost btn--xs">Place photo &amp; name</a>
                                <button type="submit" form="remove-design-{{ $upload->id }}" class="btn btn--danger btn--xs">Remove</button>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="box">
            <h2>Words on the built-in designs</h2>
            @if ($canLead)
                <label class="field">Headline
                    <input type="text" name="headline" value="{{ old('headline', $template['headline']) }}" required maxlength="30">
                    @error('headline') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Message
                    <textarea name="message" required maxlength="200" rows="2">{{ old('message', $template['message']) }}</textarea>
                    @error('message') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Verse or blessing (optional)
                    <textarea name="verse" maxlength="160" rows="2">{{ old('verse', $template['verse']) }}</textarea>
                    @error('verse') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="login__remember"><input type="checkbox" name="community" value="1" @checked($community)>Post each card in the community on the day</label>
                <div><button type="submit" class="btn btn--gold">Save</button></div>
            @else
                <dl class="detail-list">
                    <dt>Headline</dt><dd>{{ $template['headline'] }}</dd>
                    <dt>Message</dt><dd>{{ $template['message'] }}</dd>
                    <dt>Verse</dt><dd>{{ $template['verse'] ?: 'None' }}</dd>
                </dl>
                <p class="box__note" style="margin: 0">Your HOD chooses the designs and words.</p>
            @endif
        </section>
    </form>
    @foreach ($uploads as $upload)
        <form id="remove-design-{{ $upload->id }}" method="POST" action="{{ route('unit.birthdays.designs.destroy', [$unit, $upload]) }}" data-confirm="Remove the design &quot;{{ $upload->name }}&quot;?">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

    @if ($canLead)
        <section class="box">
            <h2>Upload your own design</h2>
            <p class="box__note" style="margin: 0">Made a birthday design in Canva, Photoshop or anywhere else? Upload it here, leaving space for the photo and name, then place them on it. Portrait, 1080 × 1350 (4:5), works best.</p>
            <form method="POST" action="{{ route('unit.birthdays.designs.store', $unit) }}" enctype="multipart/form-data" class="form-row">
                @csrf
                <label class="field">Name it
                    <input type="text" name="name" required maxlength="80" placeholder="e.g. Christmas birthdays" value="{{ old('name') }}">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">The design (JPG, PNG or WebP)
                    <input type="file" name="image" required accept="image/jpeg,image/png,image/webp">
                    @error('image') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div style="align-self: end"><button type="submit" class="btn btn--navy">Upload</button></div>
            </form>
        </section>
    @endif

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
                        <p class="box__note" style="margin: 0">Their design this year: {{ $designs[$cards->designFor($m)] ?? 'Royal crown' }}.</p>
                        <form method="GET" action="{{ route('unit.birthdays.card', [$unit, $m]) }}" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
                            <input type="hidden" name="download" value="1">
                            <label class="sr-only" for="design-{{ $m->id }}">Design</label>
                            <select id="design-{{ $m->id }}" name="design" class="select-sm">
                                @foreach ($designs as $key => $label)
                                    <option value="{{ $key }}" @selected($key === $cards->designFor($m))>{{ \Illuminate\Support\Str::before($label, ':') }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn--ghost btn--xs">Download card</button>
                        </form>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap">
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

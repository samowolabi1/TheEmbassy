@extends('layouts.app', ['title' => 'Ask the media team', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
        <span class="eyebrow">MEDIA REQUESTS</span>
        <h1 class="display notices-hero__title">Ask the media team.</h1>
        <p class="lede" style="max-width: 680px">Flyers, graphics, videos, photos of your event, a livestream, slides. Tell the {{ $mediaUnit?->name ?? 'media unit' }} what you need and when, and follow it here. Please give at least {{ \App\Models\MediaRequest::NOTICE_DAYS }} days' notice.</p>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif

        @if ($requests->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Your requests</h2>
                @foreach ($requests as $r)
                    <a href="{{ route('media-requests.show', $r) }}" class="package-row" style="color: inherit; text-decoration: none">
                        <span class="stack" style="gap: 2px">
                            <strong>{{ $r->title }}</strong>
                            <span class="field-hint">{{ $r->typeLabel() }} · {{ $r->fromLabel() }} · needed by {{ $r->needed_by->format('D j M') }} · {{ $r->reference }}</span>
                        </span>
                        <span class="status-chip {{ in_array($r->status, ['delivered', 'completed']) ? 'status-chip--ok' : '' }}">{{ $r->statusLabel() }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('media-requests.store') }}" enctype="multipart/form-data" class="card notice-form__main" style="flex: none">
            @csrf
            <fieldset class="notice-form__group">
                <legend>New request</legend>
                <div class="form-row">
                    <label class="field">For
                        <select name="unit_id" @required(! auth()->user()->isStaff())>
                            @if (auth()->user()->isStaff())
                                <option value="">Church office</option>
                            @endif
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" @selected((int) old('unit_id') === $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        @error('unit_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">What do you need?
                        <select name="type" required>
                            @foreach (\App\Models\MediaRequest::TYPES as $key => $label)
                                <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label class="field">In a few words
                    <input type="text" name="title" value="{{ old('title') }}" required maxlength="150" placeholder="e.g. Flyer for the choir's Christmas concert">
                    @error('title') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Details
                    <textarea name="details" required maxlength="5000" style="height: 150px" placeholder="The wording to use, dates, times and venue, who it's for, sizes (e.g. Instagram square, A4 print), colours or anything else.">{{ old('details') }}</textarea>
                    @error('details') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div class="form-row">
                    <label class="field">Date of the event (if any)
                        <input type="date" name="event_on" value="{{ old('event_on') }}" min="{{ today()->toDateString() }}">
                        @error('event_on') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Needed by
                        <input type="date" name="needed_by" value="{{ old('needed_by', today()->addDays(\App\Models\MediaRequest::NOTICE_DAYS)->toDateString()) }}" min="{{ today()->toDateString() }}" required>
                        @error('needed_by') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label class="field">Attachments (optional)
                    <input type="file" name="files[]" multiple>
                    <span class="field-hint">Logos, photos to use, examples you like, the programme. Up to 5 files, 25 MB each.</span>
                    @error('files') <span class="field-error">{{ $message }}</span> @enderror
                    @error('files.*') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <button type="submit" class="btn btn--gold" style="align-self: flex-start">Send to the media team</button>
            </fieldset>
        </form>
    </section>
@endsection

@extends('layouts.admin', ['title' => $sermon->exists ? 'Edit message' : 'Add a message', 'active' => 'live'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.sermons.index') }}" class="box__link">← Sermons &amp; live</a>
            <h1>{{ $sermon->exists ? 'Edit message' : 'Add a message' }}</h1>
        </div>
    </header>

    <form method="POST" action="{{ $sermon->exists ? route('admin.sermons.update', $sermon) : route('admin.sermons.store') }}" class="row">
        @csrf
        @if ($sermon->exists) @method('PUT') @endif
        <section class="box grow form">
            <label class="field">Title
                <input type="text" name="title" value="{{ old('title', $sermon->title) }}" required maxlength="120">
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <div class="form-row">
                <label class="field">Type
                    <select name="kind">
                        @foreach ($kinds as $key => $label)
                            <option value="{{ $key }}" @selected(old('kind', $sermon->kind) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">Series
                    <select name="sermon_series_id">
                        <option value="">None</option>
                        @foreach ($series as $s)
                            <option value="{{ $s->id }}" @selected((int) old('sermon_series_id', $sermon->sermon_series_id) === $s->id)>{{ $s->fullTitle() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="form-row">
                <label class="field">Preacher <input type="text" name="preacher" value="{{ old('preacher', $sermon->preacher) }}" maxlength="100"></label>
                <label class="field">Date
                    <input type="date" name="preached_on" value="{{ old('preached_on', $sermon->preached_on?->toDateString()) }}" required>
                    @error('preached_on') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <div class="form-row">
                <label class="field">Scripture <input type="text" name="scripture" value="{{ old('scripture', $sermon->scripture) }}" maxlength="60" placeholder="e.g. 1 Peter 2:9"></label>
                <label class="field">Length <input type="text" name="length" value="{{ old('length', $sermon->length) }}" maxlength="20" placeholder="e.g. 42 min"></label>
            </div>
            <label class="field">Link (YouTube, Spotify…)
                <input type="url" name="url" value="{{ old('url', $sermon->url) }}" maxlength="500" placeholder="https://">
                @error('url') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </section>
        <section class="box side-col form">
            <label class="field">Thumbnail colour
                <select name="swatch">
                    @foreach ($swatches as $swatch)
                        <option value="{{ $swatch }}" @selected(old('swatch', $sermon->swatch) === $swatch)>{{ ucfirst($swatch) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="login__remember"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $sermon->is_published))>Show on the website</label>
            <button type="submit" class="btn btn--navy">{{ $sermon->exists ? 'Save' : 'Add message' }}</button>
            @if ($sermon->exists)
                <button type="submit" form="delete-sermon" class="btn btn--danger btn--xs">Remove</button>
            @endif
        </section>
    </form>
    @if ($sermon->exists)
        <form id="delete-sermon" method="POST" action="{{ route('admin.sermons.destroy', $sermon) }}" data-confirm="Remove this message from the library?">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection

{{-- Fields shared by the new and edit announcement forms. Expects $announcement (may be unsaved). --}}
@php
    $status = $announcement->exists ? $announcement->status() : 'live';
    $when = old('when', match ($status) { 'scheduled' => 'schedule', 'draft' => 'draft', default => 'now' });
    $publishAt = old('publish_at', $status === 'scheduled' ? $announcement->published_at->format('Y-m-d\TH:i') : '');
@endphp

<label class="field">Title
    <input type="text" name="title" value="{{ old('title', $announcement->title) }}" required maxlength="120" data-preview-source="title"
        @error('title') aria-invalid="true" @enderror>
    @error('title') <span class="field-error">{{ $message }}</span> @enderror
</label>
<label class="field">Message
    <textarea name="body" required maxlength="500" data-preview-source="body" @error('body') aria-invalid="true" @enderror>{{ old('body', $announcement->body) }}</textarea>
    @error('body') <span class="field-error">{{ $message }}</span> @enderror
</label>
<div class="form-row">
    <label class="field">Tag
        <input type="text" name="tag" value="{{ old('tag', $announcement->tag) }}" required maxlength="40" placeholder="e.g. This Sunday"
            @error('tag') aria-invalid="true" @enderror>
        <span class="field__hint">The small label beside it, like "Units" or "Prayer".</span>
        @error('tag') <span class="field-error">{{ $message }}</span> @enderror
    </label>
    <label class="field">Link (optional)
        <input type="text" name="url" value="{{ old('url', $announcement->url) }}" maxlength="255" placeholder="/events or https://…"
            @error('url') aria-invalid="true" @enderror>
        <span class="field__hint">Where it goes when tapped.</span>
        @error('url') <span class="field-error">{{ $message }}</span> @enderror
    </label>
</div>

<fieldset class="choice-group">
    <legend>Tag colour</legend>
    <div class="choices">
        @foreach (\App\Models\Announcement::TONES as $tone => $label)
            <label class="choice"><input type="radio" name="tone" value="{{ $tone }}" @checked(old('tone', $announcement->tone ?? 'wash') === $tone)><span>{{ $label }}</span></label>
        @endforeach
    </div>
</fieldset>

<fieldset class="choice-group">
    <legend>When</legend>
    <div class="choices">
        <label class="choice"><input type="radio" name="when" value="now" @checked($when === 'now')><span>{{ $status === 'live' && $announcement->exists ? 'Keep live' : 'Publish now' }}</span></label>
        <label class="choice"><input type="radio" name="when" value="schedule" @checked($when === 'schedule')><span>Schedule for later</span></label>
        <label class="choice"><input type="radio" name="when" value="draft" @checked($when === 'draft')><span>Save as draft</span></label>
    </div>
    <label class="field" data-schedule-at>Go live at
        <input type="datetime-local" name="publish_at" value="{{ $publishAt }}" @error('publish_at') aria-invalid="true" @enderror>
        <span class="field__hint">Lagos time.</span>
        @error('publish_at') <span class="field-error">{{ $message }}</span> @enderror
    </label>
</fieldset>

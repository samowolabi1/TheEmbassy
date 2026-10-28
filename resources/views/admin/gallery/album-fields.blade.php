<label class="field">Title
    <input type="text" name="title" value="{{ old('title', $album->title) }}" required maxlength="120" placeholder="e.g. Royal Youth Convention 2026">
    @error('title') <span class="field-error">{{ $message }}</span> @enderror
</label>
<label class="field">Date
    <input type="date" name="taken_on" value="{{ old('taken_on', $album->taken_on?->toDateString()) }}">
</label>
<label class="field">Event (optional)
    <select name="event_id">
        <option value="">None</option>
        @foreach ($events as $event)
            <option value="{{ $event->id }}" @selected((int) old('event_id', $album->event_id) === $event->id)>{{ $event->title }} · {{ $event->starts_at->format('j M Y') }}</option>
        @endforeach
    </select>
</label>
<label class="field">Description
    <textarea name="description" maxlength="2000">{{ old('description', $album->description) }}</textarea>
</label>

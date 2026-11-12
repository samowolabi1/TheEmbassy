<label class="field">Title
    <input type="text" name="title" value="{{ old('title', $package->title) }}" required maxlength="120" placeholder="e.g. Christmas food package">
    @error('title') <span class="field-error">{{ $message }}</span> @enderror
</label>
<label class="field">About it (members see this)
    <textarea name="description" maxlength="2000" placeholder="Who it's for and anything to know">{{ old('description', $package->description) }}</textarea>
</label>
<label class="field">What's in it <input type="text" name="items" value="{{ old('items', $package->items) }}" maxlength="500" placeholder="e.g. Rice, beans, oil, tin tomatoes, noodles"></label>
<div class="form-row">
    <label class="field">Applications open <input type="datetime-local" name="opens_at" value="{{ old('opens_at', $package->opens_at?->format('Y-m-d\TH:i')) }}" required></label>
    <label class="field">Applications close
        <input type="datetime-local" name="closes_at" value="{{ old('closes_at', $package->closes_at?->format('Y-m-d\TH:i')) }}" required>
        @error('closes_at') <span class="field-error">{{ $message }}</span> @enderror
    </label>
</div>
<label class="field">Number of packages (optional)
    <input type="number" name="slots" value="{{ old('slots', $package->slots) }}" min="1" max="100000" placeholder="Leave empty for no fixed number">
</label>
<label class="field">Where and when to collect <input type="text" name="collection_details" value="{{ old('collection_details', $package->collection_details) }}" maxlength="500" placeholder="e.g. Church office, Saturday 20 Dec, 10am–2pm"></label>
<label class="login__remember"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $package->is_published))>Members can see it and apply while it's open</label>

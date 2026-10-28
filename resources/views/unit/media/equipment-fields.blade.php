<div class="form-row">
    <label class="field">Name <input type="text" name="name" value="{{ old('name', $item->name) }}" required maxlength="120" placeholder="e.g. Sony FX30 camera">@error('name') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label class="field">Category
        <select name="category">
            @foreach (\App\Models\Equipment::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected(old('category', $item->category) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">Asset tag <input type="text" name="tag" value="{{ old('tag', $item->tag) }}" maxlength="30" placeholder="e.g. CAM-01">@error('tag') <span class="field-error">{{ $message }}</span> @enderror</label>
</div>
<div class="form-row">
    <label class="field">Serial number <input type="text" name="serial" value="{{ old('serial', $item->serial) }}" maxlength="120"></label>
    <label class="field">Kept where <input type="text" name="location" value="{{ old('location', $item->location) }}" maxlength="120" placeholder="e.g. Media room cabinet"></label>
    <label class="field">Condition
        <select name="condition">
            @foreach (['good' => 'Good', 'fair' => 'Fair', 'faulty' => 'Faulty'] as $key => $label)
                <option value="{{ $key }}" @selected(old('condition', $item->condition) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
</div>
<div class="form-row">
    <label class="field">Bought on <input type="date" name="purchased_on" value="{{ old('purchased_on', $item->purchased_on?->toDateString()) }}"></label>
    <label class="field">Value (₦) <input type="number" name="value" value="{{ old('value', $item->value ? $item->value / 100 : '') }}" min="0" step="1"></label>
</div>
<label class="field">Notes <input type="text" name="notes" value="{{ old('notes', $item->notes) }}" maxlength="2000"></label>

{{-- The request fields shared by the member form and the welfare team's form. --}}
<div class="form-row">
    <label class="field">What kind of help
        <select name="category" required>
            @foreach (config('welfare.categories') as $key => $label)
                <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">How soon
        <select name="urgency">
            <option value="normal" @selected(old('urgency') !== 'urgent')>When you can</option>
            <option value="urgent" @selected(old('urgency') === 'urgent')>Urgent</option>
        </select>
    </label>
    <label class="field">Amount needed, if any (₦)
        <input type="number" name="amount_requested" value="{{ old('amount_requested') }}" min="0" step="1">
        @error('amount_requested') <span class="field-error">{{ $message }}</span> @enderror
    </label>
</div>
<label class="field">In a sentence
    <input type="text" name="summary" value="{{ old('summary') }}" required maxlength="160" placeholder="e.g. Hospital bill after surgery">
    @error('summary') <span class="field-error">{{ $message }}</span> @enderror
</label>
<label class="field">More detail (optional)
    <textarea name="details" maxlength="5000" style="height: 120px">{{ old('details') }}</textarea>
</label>

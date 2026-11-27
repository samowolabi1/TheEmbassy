<label class="field">Reference
    <input type="text" name="reference" value="{{ old('reference', $verse->reference) }}" required maxlength="60" placeholder="e.g. Psalm 23:1 or Romans 8:38-39">
</label>
<label class="field">The verse (KJV)
    <textarea name="text" rows="4" required maxlength="1500" style="height: auto">{{ old('text', $verse->text) }}</textarea>
</label>
<label class="field">Theme
    <select name="theme">
        <option value="">None</option>
        @foreach (\App\Models\Verse::THEMES as $t)
            <option value="{{ $t }}" @selected(old('theme', $verse->theme) === $t)>{{ $t }}</option>
        @endforeach
    </select>
</label>

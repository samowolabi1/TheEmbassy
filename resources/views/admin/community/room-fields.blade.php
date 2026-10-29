<div class="form-row">
    <label class="field">Name <input type="text" name="name" value="{{ $r->name }}" required maxlength="60"></label>
    <label class="field">Emoji <input type="text" name="emoji" value="{{ $r->emoji }}" maxlength="16" placeholder="e.g. 🙏"></label>
</div>
<label class="field">What it's for <input type="text" name="description" value="{{ $r->description }}" maxlength="200"></label>
<div class="form-row">
    <label class="field">Only for a unit
        <select name="unit_id">
            <option value="">Everyone</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected($r->unit_id === $unit->id)>{{ $unit->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">Order <input type="number" name="position" value="{{ $r->position }}" min="0" max="999"></label>
</div>
<label class="login__remember"><input type="checkbox" name="is_locked" value="1" @checked($r->is_locked)>Announcements only (only moderators post)</label>

<div class="form-row">
    <label class="field">Title <input type="text" name="title" value="{{ $s->title }}" required maxlength="40" placeholder="e.g. Crowned"></label>
    <label class="field">Second line (gold) <input type="text" name="accent" value="{{ $s->accent }}" maxlength="40" placeholder="e.g. for More"></label>
</div>
<div class="form-row">
    <label class="field">Card colour
        <select name="style">
            @foreach (\App\Models\SermonSeries::STYLES as $key => $label)
                <option value="{{ $key }}" @selected($s->style === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">Playlist link (optional) <input type="url" name="url" value="{{ $s->url }}" maxlength="500" placeholder="https://youtube.com/playlist?list=…"></label>
</div>
<label class="login__remember"><input type="checkbox" name="is_current" value="1" @checked($s->is_current)>Now showing (featured on the home page)</label>

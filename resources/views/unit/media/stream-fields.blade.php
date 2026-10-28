<div class="form-row">
    <label class="field">Platform
        <select name="platform">
            @foreach (\App\Models\StreamDestination::PLATFORMS as $key => $label)
                <option value="{{ $key }}" @selected($d->platform === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">Name <input type="text" name="label" value="{{ $d->label }}" required maxlength="100" placeholder="e.g. YouTube main channel"></label>
</div>
<div class="form-row">
    <label class="field">Server URL <input type="text" name="server_url" value="{{ $d->server_url }}" required maxlength="500" placeholder="rtmp://a.rtmp.youtube.com/live2">@error('server_url') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label class="field">Stream key <input type="password" name="stream_key" maxlength="500" autocomplete="off" placeholder="{{ $d->exists ? 'Leave empty to keep the saved key' : 'Paste the stream key' }}" @unless ($d->exists) required @endunless>@error('stream_key') <span class="field-error">{{ $message }}</span> @enderror</label>
</div>

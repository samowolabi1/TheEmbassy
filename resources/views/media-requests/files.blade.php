{{-- Files on a request: what was sent with it, or what the media team delivered. --}}
<ul class="mini-list" style="margin: 0">
    @foreach ($files as $file)
        <li style="flex-wrap: wrap; gap: 8px 16px">
            <span style="display: flex; gap: 10px; align-items: center; min-width: 0; flex: 1 1 180px">
                @if ($file->thumb_path)
                    <img src="{{ route('media-files.show', [$file, 'thumb']) }}" alt="" width="44" height="44" style="width: 44px; height: 44px; object-fit: cover; border-radius: 10px; flex: none">
                @endif
                <span style="min-width: 0; overflow-wrap: break-word; word-break: break-word">{{ $file->isLink() ? $file->url : $file->original_name }}</span>
            </span>
            <span style="display: flex; gap: 12px; flex: none">
                @if ($file->isLink())
                    <a href="{{ $file->url }}" target="_blank" rel="noopener noreferrer" style="font-weight: 700">Open link</a>
                @else
                    @if ($file->showsInline())
                        <a href="{{ route('media-files.show', [$file, 'view']) }}" target="_blank" rel="noopener" style="font-weight: 700">View</a>
                    @endif
                    <a href="{{ route('media-files.show', [$file, 'download']) }}" style="font-weight: 700">Download <span class="field-hint">({{ $file->sizeLabel() }})</span></a>
                @endif
            </span>
        </li>
    @endforeach
</ul>

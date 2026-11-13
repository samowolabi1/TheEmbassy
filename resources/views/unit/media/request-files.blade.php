<div class="list-thumbs" style="display: flex; flex-direction: column; gap: 8px">
    @foreach ($files as $file)
        <div class="list-item" style="padding: 8px 0">
            @if ($file->thumb_path)
                <img src="{{ route('media-files.show', [$file, 'thumb']) }}" alt="" class="list-thumb" style="width: 48px; height: 48px; object-fit: cover; border-radius: 10px">
            @else
                <span class="avatar" aria-hidden="true">{{ strtoupper(mb_substr($file->isLink() ? 'L' : ($file->extension() ?: '?'), 0, 3)) }}</span>
            @endif
            <span class="list-item__main">
                <span class="list-item__title" style="overflow-wrap: anywhere">{{ $file->isLink() ? $file->url : $file->original_name }}</span>
                <span class="list-item__meta">{{ $file->kindLabel() }}{{ $file->size ? ' · '.$file->sizeLabel() : '' }}{{ $file->uploader ? ' · '.($file->uploader->member?->name ?? $file->uploader->name) : '' }}</span>
            </span>
            @if ($file->isLink())
                <a href="{{ $file->url }}" target="_blank" rel="noopener noreferrer" class="btn btn--ghost btn--xs">Open</a>
            @else
                @if ($file->showsInline())<a href="{{ route('media-files.show', [$file, 'view']) }}" target="_blank" rel="noopener" class="btn btn--ghost btn--xs">View</a>@endif
                <a href="{{ route('media-files.show', [$file, 'download']) }}" class="btn btn--ghost btn--xs">Download</a>
            @endif
        </div>
    @endforeach
</div>

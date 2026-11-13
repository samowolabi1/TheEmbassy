{{-- The share kit for one shop or listing. Needs $item, $cards, $kind ('product' or 'shop'), $id (for element ids). --}}
@php
    $image = fn (string $format, bool $download = false) => route($kind === 'shop' ? 'market.shop.image' : 'market.product.image', [$item, $format, ...($download ? ['download' => 1] : [])]);
@endphp
<div class="share-kit">
    <a href="{{ $image('square') }}" target="_blank" rel="noopener" class="share-kit__preview">
        <img src="{{ $image('square') }}" alt="The picture to share for {{ $item->name }}" width="1080" height="1080" loading="lazy">
    </a>
    <div class="stack" style="gap: 12px; min-width: 0">
        <label class="field">Caption (edit it if you like, then copy)
            <textarea id="caption-{{ $id }}" rows="6">{{ $cards->caption($item, 'share') }}</textarea>
        </label>
        <div style="display: flex; gap: 8px; flex-wrap: wrap">
            <button type="button" class="btn btn--navy btn--xs" data-copy-from="#caption-{{ $id }}">Copy caption</button>
        </div>
        @include('market.share-bar', ['item' => $item, 'cards' => $cards, 'image' => $image('square')])
        <div class="stack" style="gap: 6px">
            <span class="field__hint" style="font-weight: 700">Download pictures</span>
            <div style="display: flex; gap: 8px; flex-wrap: wrap">
                @foreach (\App\Services\Market\ShareCards::FORMATS as $format => [$w, $h, $label])
                    <a href="{{ $image($format, true) }}" class="btn btn--ghost btn--xs">{{ $label }}</a>
                @endforeach
                <a href="{{ $image('qr', true) }}" class="btn btn--ghost btn--xs">QR code (for flyers)</a>
            </div>
        </div>
        <span class="field__hint">Visits from links shared here: {{ number_format($item->share_visits) }}</span>
    </div>
</div>

{{--
    Share buttons for a shop or a listing. Each link is marked with where it was shared, so the
    seller can see visits from their shared links.
    Needs: $item (Product or Shop), $cards (ShareCards). Optional: $image (a share picture URL for the phone share sheet).
--}}
@php
    $u = fn (string $via) => $cards->url($item, $via);
    $caption = fn (string $via) => $cards->caption($item, $via);
    $name = $item instanceof \App\Models\Product ? $item->name : $item->name;
@endphp
<div class="share-bar" role="group" aria-label="Share {{ $name }}">
    <button type="button" class="btn btn--gold btn--sm" hidden data-share-native data-share-text="{{ $caption('share') }}" data-share-title="{{ $name }}" @isset($image) data-share-image="{{ $image }}" @endisset>Share…</button>
    <a href="https://wa.me/?text={{ rawurlencode($caption('wa')) }}" target="_blank" rel="noopener" class="btn btn--outline btn--sm">WhatsApp</a>
    <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($u('fb')) }}" target="_blank" rel="noopener" class="btn btn--outline btn--sm">Facebook</a>
    <a href="https://x.com/intent/post?text={{ rawurlencode(\Illuminate\Support\Str::before($caption('x'), "\n")) }}&url={{ rawurlencode($u('x')) }}" target="_blank" rel="noopener" class="btn btn--outline btn--sm">X</a>
    <a href="https://t.me/share/url?url={{ rawurlencode($u('tg')) }}&text={{ rawurlencode(\Illuminate\Support\Str::before($caption('tg'), "\n")) }}" target="_blank" rel="noopener" class="btn btn--outline btn--sm">Telegram</a>
    <button type="button" class="btn btn--outline btn--sm" data-copy="{{ $u('link') }}" data-copy-label="Copy link">Copy link</button>
</div>

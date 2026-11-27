{{-- One verse in the scroll. The same markup is the template the script copies for more verses. --}}
<article @class(['verse-card', 'verse-card--'.($v['id'] % 6), 'verse-card--long' => mb_strlen($v['text']) > 200]) data-verse="{{ $v['id'] }}" data-ref="{{ $v['reference'] }}">
    <span class="verse-card__theme" data-theme-label>{{ $v['theme'] }}</span>
    <blockquote class="verse-card__text" data-text>{{ $v['text'] }}</blockquote>
    <cite class="verse-card__ref"><span data-ref-label>{{ $v['reference'] }}</span> · KJV</cite>
    <div class="verse-card__actions">
        <form method="POST" action="{{ route('account.verses.save', ['verse' => $v['id'] ?: 1]) }}" data-save-form>
            @csrf
            <button type="submit" class="verse-btn" data-save aria-pressed="{{ $isSaved ? 'true' : 'false' }}">
                <span aria-hidden="true">{{ $isSaved ? '♥' : '♡' }}</span><span data-save-label>{{ $isSaved ? 'Saved' : 'Save' }}</span>
            </button>
        </form>
        <button type="button" class="verse-btn" data-share><span aria-hidden="true">↗</span>Share</button>
        <button type="button" class="verse-btn" data-copy><span aria-hidden="true">⧉</span><span data-copy-label>Copy</span></button>
    </div>
    @if ($first)
        <span class="verse-card__hint" aria-hidden="true">Swipe up or tap for another verse</span>
    @endif
</article>

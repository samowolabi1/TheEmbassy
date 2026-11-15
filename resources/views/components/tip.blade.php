{{-- A small ⓘ beside a field or button: hover, focus or tap it to read a short explanation. --}}
@props(['text'])
@php($id = 'tip-'.substr(md5($text.uniqid('', true)), 0, 8))
<span class="tip">
    <button type="button" class="tip__button" aria-label="More about this" aria-describedby="{{ $id }}" data-tip>i</button>
    <span class="tip__text" role="tooltip" id="{{ $id }}">{{ $text }}</span>
</span>

{{-- Inline stroke icons used across the site. Decorative by default; pass a label to expose one. --}}
@props(['name', 'size' => 20, 'label' => null])

@php
    $icons = [
        'play' => ['fill' => true, 'body' => '<path d="M8 5v14l11-7z"/>'],
        'youtube' => ['body' => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="M10 9l5 3-5 3z" fill="currentColor"/>'],
        'instagram' => ['body' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>'],
        'tiktok' => ['body' => '<path d="M14 3v11a4 4 0 1 1-4-4"/><path d="M14 3a5 5 0 0 0 5 5"/>'],
        'facebook' => ['body' => '<path d="M15 3h-2a4 4 0 0 0-4 4v3H7v4h2v7h4v-7h2.5l.5-4h-3V7a1 1 0 0 1 1-1h2z"/>'],
        'x' => ['body' => '<path d="M4 4l16 16M20 4L4 20"/>'],
        'whatsapp' => ['body' => '<path d="M3 21l1.6-4.6A9 9 0 1 1 8 19.5z"/>'],
        'pin' => ['body' => '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
        'prayer' => ['body' => '<path d="M7 21V11l5-8 5 8v10"/><path d="M7 15h10"/>'],
        'heart' => ['body' => '<path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6C19 16.5 12 21 12 21z"/>'],
        'headphones' => ['body' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1"/><rect x="17" y="14" width="4" height="6" rx="1"/>'],
        'search' => ['body' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>'],
        'calendar' => ['body' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>'],
        'chevron-left' => ['body' => '<path d="M15 6l-6 6 6 6"/>'],
        'chevron-right' => ['body' => '<path d="M9 6l6 6-6 6"/>'],
        'menu' => ['body' => '<path d="M4 7h16M4 12h16M4 17h10"/>'],
        'close' => ['body' => '<path d="M6 6l12 12M18 6L6 18"/>'],
        'lock' => ['body' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>'],
        'check' => ['body' => '<path d="M5 12l5 5 9-10"/>'],
        'bank' => ['body' => '<path d="M3 10l9-6 9 6"/><path d="M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/>'],
        'phone' => ['body' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>'],
        'link' => ['body' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>'],
        'plus' => ['body' => '<path d="M12 5v14M5 12h14"/>'],
        'minus' => ['body' => '<path d="M5 12h14"/>'],
        'sign-out' => ['body' => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4M6 12h10"/>'],
    ];
    $icon = $icons[$name];
    $filled = $icon['fill'] ?? false;
@endphp

<svg {{ $attributes }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
    @if ($filled) fill="currentColor" @else fill="none" stroke="currentColor" stroke-width="2" @endif
    stroke-linecap="round" stroke-linejoin="round"
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>{!! $icon['body'] !!}</svg>

{{-- The crown from the logo, used as the site's graphic motif.
     `jewels` adds the three jewels on its points; `band` adds the band underneath. --}}
@props([
    'width',
    'height' => null,
    'fill' => 'var(--gold)',
    'jewels' => false,
    'band' => false,
    'label' => null,
])

<svg {{ $attributes }} width="{{ $width }}" height="{{ $height ?? round($width * 20 / 24) }}" viewBox="0 0 24 20" fill="{{ $fill }}"
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>
    <path d="M2 16h20l-2-10-5 4-3-7-3 7-5-4z"/>
    @if ($jewels)
        <circle cx="2.2" cy="5.5" r="1.4"/><circle cx="12" cy="2" r="1.4"/><circle cx="21.8" cy="5.5" r="1.4"/>
    @endif
    @if ($band)
        <rect x="2" y="17" width="20" height="2" rx="1"/>
    @endif
</svg>

{{-- Favicon, browser theme colour and any brand colour overrides from config/church.php. --}}
<link rel="icon" href="{{ asset(config('church.brand.logo')) }}">
@php
    // Only plain hex colours get through, so a bad value can't inject CSS.
    $overrides = collect(config('church.brand.colors'))
        ->filter(fn ($hex) => is_string($hex) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $hex));
@endphp
<meta name="theme-color" content="{{ $overrides->get('navy', '#0A1D3F') }}">
@if ($overrides->isNotEmpty())
    <style>:root { @foreach ($overrides as $name => $hex) --{{ $name }}: {{ $hex }}; @endforeach }</style>
@endif

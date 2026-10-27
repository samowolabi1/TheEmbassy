@php
    $title = isset($title) ? $title.' · '.config('church.name') : config('church.name');
    $description ??= config('church.description');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cinzel:wght@700;800&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme')
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    @include('partials.header', ['active' => $active ?? null])
    <main id="main">
        @yield('content')
    </main>
    @include('partials.footer')
</body>
</html>

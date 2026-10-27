<?php

// Church details, branding and links used across the site.
// To deploy for another church, change this file and config/content/, and replace the logo in public/images.
// Values in [brackets] are placeholders from the design: replace them with real details.

$marketName = env('CHURCH_MARKET_NAME', 'Tribe Market');

return [

    'name' => env('CHURCH_NAME', 'RCCG The Royal Tribe Embassy'),
    'short_name' => env('CHURCH_SHORT_NAME', 'The Royal Tribe Embassy'),

    'brand' => [
        // The two-line wordmark beside the logo.
        'wordmark' => env('CHURCH_WORDMARK', 'THE ROYAL TRIBE'),
        'wordmark_sub' => env('CHURCH_WORDMARK_SUB', 'EMBASSY'),
        // Path under public/.
        'logo' => env('CHURCH_LOGO', 'images/royal-tribe-logo.jpg'),
        'app_name' => env('CHURCH_APP_NAME', 'The Royal Tribe app'),
        'market_name' => $marketName,
        // Optional colour overrides (hex). Empty keeps the design's royal navy and crown gold.
        'colors' => [
            'navy' => env('BRAND_PRIMARY'),
            'gold' => env('BRAND_ACCENT'),
            'midnight' => env('BRAND_ACCENT_TEXT'),
        ],
    ],

    'tagline' => 'A youth parish of The Redeemed Christian Church of God.',
    'description' => 'A youth parish of The Redeemed Christian Church of God for students, creatives and young professionals.',
    'address' => '[Venue address]',
    'sunday_time' => '[Time]',
    'midweek_day' => '[Midweek day]',
    'midweek_time' => '[Time]',
    'handle' => '@[yourhandle]',
    'directions_url' => '#',

    'bank' => [
        'name' => '[Bank name]',
        'account_number' => '[Account number]',
        'account_name' => 'RCCG The Royal Tribe Embassy',
    ],

    'links' => [
        'youtube' => '#',
        'youtube_live' => '#',
        'instagram' => '#',
        'tiktok' => '#',
        'facebook' => '#',
        'x' => '#',
        'whatsapp' => '#',
        'spotify' => '#',
        'apple_podcasts' => '#',
        'audiomack' => '#',
        'app_store' => '#',
        'google_play' => '#',
        'login' => '#',
        'contact' => '#',
        'privacy' => '#',
        'terms' => '#',
        'rccg_beliefs' => 'https://www.rccg.org',
    ],

    // Primary navigation. `route` is a named route; `fragment` jumps to a section of that page.
    'nav' => [
        ['id' => 'home', 'label' => 'Home', 'route' => 'home'],
        ['id' => 'about', 'label' => 'About', 'route' => 'about'],
        ['id' => 'watch', 'label' => 'Watch', 'route' => 'watch'],
        ['id' => 'blog', 'label' => 'Blog', 'route' => 'blog.index'],
        ['id' => 'events', 'label' => 'Events', 'route' => 'events'],
        ['id' => 'market', 'label' => $marketName, 'route' => 'home', 'fragment' => 'market'],
        ['id' => 'prayer', 'label' => 'Prayer', 'route' => 'home', 'fragment' => 'prayer'],
    ],

    // Shown in the gold bar above the header. Set to null to hide it.
    'banner' => [
        'text' => 'Royal Youth Convention tickets are open',
        'cta' => 'Register now',
        'route' => 'events',
    ],

];

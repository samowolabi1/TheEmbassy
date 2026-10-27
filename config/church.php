<?php

// Church details and links used across the site.
// Values in [brackets] are placeholders from the design: replace them with real details.

return [

    'name' => 'RCCG The Royal Tribe Embassy',
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
        ['id' => 'market', 'label' => 'Tribe Market', 'route' => 'home', 'fragment' => 'market'],
        ['id' => 'prayer', 'label' => 'Prayer', 'route' => 'home', 'fragment' => 'prayer'],
    ],

    // Shown in the gold bar above the header. Set to null to hide it.
    'banner' => [
        'text' => 'Royal Youth Convention tickets are open',
        'cta' => 'Register now',
        'route' => 'events',
    ],

    // Paystack public key for online giving. Leave empty until the account is set up.
    'paystack_public_key' => env('PAYSTACK_PUBLIC_KEY', ''),

];

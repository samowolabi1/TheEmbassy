<?php

// Church details, branding and links used across the site.
// To deploy for another church, change this file and config/content/, and replace the logo in public/images.
// Values in [brackets] are placeholders: the church fills them in at admin › Settings & roles (config/settings.php).

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
    'map_embed_url' => null,
    'service_length' => '[length]',
    'parking' => '[Parking details]',
    'phone' => null,
    'email' => env('CHURCH_EMAIL'),

    // Members checking themselves in to services. Set in admin › Settings & roles.
    'checkin' => [
        // The venue. In-person check-in only works within `radius` metres of it.
        'latitude' => null,
        'longitude' => null,
        'radius' => 150,
        // Phone locations rougher than this are refused (ask them to step outside or see an usher).
        'max_accuracy' => 200,
        // Also ask for the code on the check-in screen, which changes every minute. GPS alone can be faked.
        'require_code' => true,
        // When check-in opens and closes, in minutes around the service start and end.
        'opens_before' => 30,
        'closes_after' => 30,
    ],

    // Welfare: the HOD approves support up to `approval_limit` naira; above that a pastor approves.
    'welfare' => [
        'approval_limit' => 50000,
        'monthly_budget' => null,
    ],

    // Pastoral care: who to check on.
    'care' => [
        // Raise a follow-up when an active member has missed this many Sunday services in a row.
        'absent_after' => 3,
        // Days to call a first-timer, and to welcome a newly approved member.
        'first_timer_days' => 2,
        'new_member_days' => 7,
    ],

    // YouTube broadcasts the app creates once the channel is connected.
    'youtube' => [
        // Create broadcasts this many days ahead for services with online check-in (0 = only by hand).
        'days_ahead' => 7,
        // public or unlisted.
        'privacy' => 'public',
    ],

    // The Watch page player. Set in admin › Settings & roles.
    'live' => [
        'video_id' => null,
        'is_live' => false,
    ],

    // The weekly email (admin › Weekly email). send_at is Lagos time on Mondays.
    'newsletter' => [
        'enabled' => true,
        'send_at' => '07:00',
    ],

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
        'rccg_beliefs' => 'https://www.rccg.org',
    ],

    // Primary navigation. `route` is a named route; `fragment` jumps to a section of that page.
    'nav' => [
        ['id' => 'home', 'label' => 'Home', 'route' => 'home'],
        ['id' => 'about', 'label' => 'About', 'route' => 'about'],
        ['id' => 'watch', 'label' => 'Watch', 'route' => 'watch'],
        ['id' => 'blog', 'label' => 'Blog', 'route' => 'blog.index'],
        ['id' => 'events', 'label' => 'Events', 'route' => 'events'],
        ['id' => 'gallery', 'label' => 'Gallery', 'route' => 'gallery.index'],
        ['id' => 'market', 'label' => $marketName, 'route' => 'market.index'],
        ['id' => 'prayer', 'label' => 'Prayer', 'route' => 'home', 'fragment' => 'prayer'],
    ],

    // Shown in the gold bar above the header. Set to null to hide it.
    'banner' => [
        'text' => 'Royal Youth Convention tickets are open',
        'cta' => 'Register now',
        'route' => 'events',
    ],

];

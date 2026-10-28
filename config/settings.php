<?php

// What the church can change in admin › Settings & roles. Each key is a config key; a saved value
// replaces the default from config/church.php or config/content/ everywhere on the site.

return [

    'church' => [
        'title' => 'Church details',
        'fields' => [
            'church.address' => ['label' => 'Venue address', 'type' => 'text'],
            'church.directions_url' => ['label' => 'Directions link (Google Maps)', 'type' => 'url'],
            'church.map_embed_url' => ['label' => 'Map embed link (Google Maps › Share › Embed › the src link)', 'type' => 'url'],
            'church.sunday_time' => ['label' => 'Sunday service time', 'type' => 'text', 'hint' => 'e.g. 9:00 AM'],
            'church.midweek_day' => ['label' => 'Midweek service day', 'type' => 'text', 'hint' => 'e.g. Wednesday'],
            'church.midweek_time' => ['label' => 'Midweek service time', 'type' => 'text'],
            'church.service_length' => ['label' => 'How long Sunday service runs', 'type' => 'text', 'hint' => 'e.g. two hours'],
            'church.parking' => ['label' => 'Parking details', 'type' => 'text'],
            'church.phone' => ['label' => 'Church phone', 'type' => 'text'],
            'church.email' => ['label' => 'Church email (contact messages go here)', 'type' => 'email'],
            'church.handle' => ['label' => 'Social media handle', 'type' => 'text', 'hint' => 'e.g. @royaltribe'],
        ],
    ],

    'leaders' => [
        'title' => 'Leaders (About page)',
        'fields' => [
            'content.about.leaders.0.name' => ['label' => 'Pastor in Charge', 'type' => 'text'],
            'content.about.leaders.1.name' => ['label' => 'Assistant Pastor', 'type' => 'text'],
            'content.about.leaders.2.name' => ['label' => 'Youth Pastor', 'type' => 'text'],
            'content.about.leaders.3.name' => ['label' => 'Coordinator, Units', 'type' => 'text'],
            'content.home.testimony.name' => ['label' => 'Home page testimony: member name', 'type' => 'text'],
            'content.home.testimony.quote' => ['label' => 'Home page testimony: words', 'type' => 'textarea'],
        ],
    ],

    'bank' => [
        'title' => 'Bank transfer details (Give page)',
        'fields' => [
            'church.bank.name' => ['label' => 'Bank', 'type' => 'text'],
            'church.bank.account_number' => ['label' => 'Account number', 'type' => 'text'],
            'church.bank.account_name' => ['label' => 'Account name', 'type' => 'text'],
        ],
    ],

    'checkin' => [
        'title' => 'Attendance check-in',
        'fields' => [
            'church.checkin.latitude' => ['label' => 'Venue latitude', 'type' => 'number', 'rules' => ['numeric', 'between:-90,90'], 'hint' => 'Stand in the church and press "Use my location" below, or copy it from Google Maps.'],
            'church.checkin.longitude' => ['label' => 'Venue longitude', 'type' => 'number', 'rules' => ['numeric', 'between:-180,180']],
            'church.checkin.radius' => ['label' => 'Check-in radius (metres)', 'type' => 'number', 'rules' => ['integer', 'between:30,2000'], 'hint' => 'How far from the venue members can check in. 100–200 m suits most buildings.'],
            'church.checkin.max_accuracy' => ['label' => 'Roughest location accepted (metres)', 'type' => 'number', 'rules' => ['integer', 'between:20,1000']],
            'church.checkin.opens_before' => ['label' => 'Check-in opens (minutes before the service)', 'type' => 'number', 'rules' => ['integer', 'between:0,240']],
            'church.checkin.closes_after' => ['label' => 'Check-in closes (minutes after the service ends)', 'type' => 'number', 'rules' => ['integer', 'between:0,240']],
            'church.checkin.require_code' => ['label' => 'In person, also ask for the code on the check-in screen (recommended: GPS alone can be faked)', 'type' => 'checkbox'],
            'church.care.absent_after' => ['label' => 'Check on members after this many missed Sundays in a row', 'type' => 'number', 'rules' => ['integer', 'between:1,12']],
        ],
    ],

    'live' => [
        'title' => 'Live stream',
        'fields' => [
            'church.live.video_id' => ['label' => 'YouTube video or live stream ID', 'type' => 'text', 'hint' => 'The part after watch?v= in the link. Shown in the player on the Watch page.'],
            'church.live.is_live' => ['label' => 'Show "Live now" on the site', 'type' => 'checkbox'],
        ],
    ],

    'links' => [
        'title' => 'Social and app links',
        'fields' => [
            'church.links.youtube' => ['label' => 'YouTube channel', 'type' => 'url'],
            'church.links.youtube_live' => ['label' => 'YouTube live page', 'type' => 'url'],
            'church.links.instagram' => ['label' => 'Instagram', 'type' => 'url'],
            'church.links.tiktok' => ['label' => 'TikTok', 'type' => 'url'],
            'church.links.facebook' => ['label' => 'Facebook', 'type' => 'url'],
            'church.links.x' => ['label' => 'X (Twitter)', 'type' => 'url'],
            'church.links.whatsapp' => ['label' => 'WhatsApp channel or group', 'type' => 'url'],
            'church.links.spotify' => ['label' => 'Spotify podcast', 'type' => 'url'],
            'church.links.apple_podcasts' => ['label' => 'Apple Podcasts', 'type' => 'url'],
            'church.links.audiomack' => ['label' => 'Audiomack', 'type' => 'url'],
            'church.links.app_store' => ['label' => 'App Store', 'type' => 'url'],
            'church.links.google_play' => ['label' => 'Google Play', 'type' => 'url'],
        ],
    ],

];

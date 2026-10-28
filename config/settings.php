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

    'welfare' => [
        'title' => 'Welfare',
        'fields' => [
            'church.welfare.approval_limit' => ['label' => 'The welfare HOD can approve up to (₦)', 'type' => 'number', 'rules' => ['integer', 'min:0', 'max:100000000'], 'hint' => 'Anything above this needs a pastor to approve.'],
            'church.welfare.monthly_budget' => ['label' => 'Monthly welfare budget (₦, optional)', 'type' => 'number', 'rules' => ['integer', 'min:0', 'max:1000000000']],
        ],
    ],

    'social' => [
        'title' => 'Social media posting (Facebook and Instagram)',
        'fields' => [
            'services.meta.page_id' => ['label' => 'Facebook Page ID', 'type' => 'text', 'hint' => 'Facebook Page › About › Page transparency, or Meta Business Suite › Settings.'],
            'services.meta.page_token' => ['label' => 'Page access token', 'type' => 'secret', 'hint' => 'A long-lived Page token with pages_manage_posts (and instagram_content_publish for Instagram). Stored encrypted.'],
            'services.meta.instagram_id' => ['label' => 'Instagram business account ID', 'type' => 'text', 'hint' => 'The Instagram professional account linked to the Page.'],
        ],
    ],

    'youtube' => [
        'title' => 'YouTube broadcasts',
        'fields' => [
            'services.google.client_id' => ['label' => 'Google OAuth client ID', 'type' => 'text', 'hint' => 'From Google Cloud › APIs & Services › Credentials (a "Web application" client with the YouTube Data API enabled). Add this site\'s /youtube/callback address as an authorised redirect URI.'],
            'services.google.client_secret' => ['label' => 'Google OAuth client secret', 'type' => 'secret'],
            'church.youtube.days_ahead' => ['label' => 'Create broadcasts this many days ahead (0 = only by hand)', 'type' => 'number', 'rules' => ['integer', 'between:0,30']],
            'church.youtube.privacy' => ['label' => 'Broadcast visibility (public or unlisted)', 'type' => 'text', 'rules' => ['in:public,unlisted']],
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

<?php

// Starting content for the home page slider and testimony; staff edit both in admin (Pages › Home page).
// Announcements, the declaration, events, notices and market products come from the admin dashboard.

return [

    // The slides at the top of the home page. Links can be a page on the site (/events), a full
    // address, or one of: @youtube (the church's channel), @sermon (the newest sermon), @share
    // (a "share this" button). "feature" adds a detail over the picture: service (next Sunday's
    // time and place), date (a date badge, from feature_text), series (the current sermon series),
    // verse (a scripture panel, from feature_text and feature_ref, in place of the picture).
    'slides' => [
        [
            'kicker' => 'RCCG YOUTH CHURCH', 'kicker_style' => 'outline',
            'title' => 'Young. Royal.', 'accent' => 'Rooted in Christ.',
            'text' => 'A home for students, creatives and young professionals. Come as you are and grow into who God called you to be.',
            'primary_label' => 'Plan your visit', 'primary_link' => '/about#visit',
            'secondary_label' => 'Watch on YouTube', 'secondary_link' => '@youtube',
            'photo' => null, 'photo_alt' => 'Sunday worship', 'swatch' => 'mid',
            'feature' => 'service', 'feature_text' => null, 'feature_ref' => null,
            'show_until' => null, 'hidden' => false,
        ],
        [
            'kicker' => 'SAT 14 NOVEMBER', 'kicker_style' => 'gold',
            'title' => 'Royal Youth', 'accent' => 'Convention',
            'text' => 'Worship, honest conversations and the biggest Tribe hangout of the year. Bring a friend.',
            'primary_label' => 'Get your ticket', 'primary_link' => '/events',
            'secondary_label' => 'Event details', 'secondary_link' => '/events',
            'photo' => null, 'photo_alt' => 'Convention artwork', 'swatch' => 'gold',
            'feature' => 'date', 'feature_text' => '14 NOV', 'feature_ref' => null,
            'show_until' => null, 'hidden' => false,
        ],
        [
            'kicker' => 'NEW SERMON SERIES', 'kicker_style' => 'outline',
            'title' => '', 'accent' => '',
            'text' => 'A series on identity, purpose and living as a royal priesthood. New episodes every Sunday.',
            'primary_label' => 'Watch now', 'primary_link' => '@sermon',
            'secondary_label' => 'All episodes', 'secondary_link' => '/watch',
            'photo' => null, 'photo_alt' => 'Series artwork', 'swatch' => 'night',
            'feature' => 'series', 'feature_text' => null, 'feature_ref' => null,
            'show_until' => null, 'hidden' => false,
        ],
        [
            'kicker' => 'THIS WEEK\'S DECLARATION', 'kicker_style' => 'outline',
            'title' => "I am chosen.\nI am royal.", 'accent' => 'I am His.',
            'text' => '',
            'primary_label' => 'Read the full declaration', 'primary_link' => '#declaration',
            'secondary_label' => 'Share it', 'secondary_link' => '@share',
            'photo' => null, 'photo_alt' => '', 'swatch' => 'night',
            'feature' => 'verse', 'feature_text' => 'But you are a chosen people, a royal priesthood, a holy nation, God\'s special possession.', 'feature_ref' => '1 PETER 2:9',
            'show_until' => null, 'hidden' => false,
        ],
    ],

    'testimony' => [
        'quote' => 'I sent a prayer request the week of my finals. The Tribe prayed me through, and now I serve in the media unit.',
        'name' => '[Member name]',
        'initials' => 'AO',
        'role' => 'Testimony · Media unit',
    ],

];

<?php

// Messages and series shown on the home and Watch pages.
// `url` is where a message plays (e.g. its YouTube link). '#' until videos are linked.
// `swatch` is the thumbnail colour: navy, gold, mid, ice, wash or night.

return [

    'filters' => [
        'all' => 'All',
        'sunday' => 'Sunday service',
        'midweek' => 'Midweek',
        'special' => 'Special services',
        'podcast' => 'Podcast',
    ],

    'messages' => [
        ['type' => 'sunday', 'kind' => 'Sunday service', 'title' => 'A royal priesthood', 'series' => 'Crowned for More', 'meta' => 'Crowned for More · Week 3', 'length' => '42 min', 'swatch' => 'navy', 'url' => '#'],
        ['type' => 'sunday', 'kind' => 'Sunday service', 'title' => 'Chosen, not forgotten', 'series' => 'Crowned for More', 'meta' => 'Crowned for More · Week 2', 'length' => '38 min', 'swatch' => 'gold', 'url' => '#'],
        ['type' => 'sunday', 'kind' => 'Sunday service', 'title' => 'Know whose you are', 'series' => 'Crowned for More', 'meta' => 'Crowned for More · Week 1', 'length' => '45 min', 'swatch' => 'mid', 'url' => '#'],
        ['type' => 'midweek', 'kind' => 'Midweek', 'title' => 'Praying with confidence', 'series' => null, 'meta' => 'Midweek prayer', 'length' => '31 min', 'swatch' => 'ice', 'url' => '#'],
        ['type' => 'podcast', 'kind' => 'Podcast', 'title' => 'Faith at work in your 20s', 'series' => null, 'meta' => 'Tribe Talk', 'length' => '29 min', 'swatch' => 'wash', 'url' => '#'],
        ['type' => 'special', 'kind' => 'Special service', 'title' => 'Thanksgiving night', 'series' => null, 'meta' => 'Special service', 'length' => '1 hr 12 min', 'swatch' => 'night', 'url' => '#'],
    ],

    // `style` is the card colour: navy, gold or ice.
    'series' => [
        ['title' => 'Crowned', 'accent' => 'for More', 'label' => 'Now showing · 3 episodes', 'style' => 'navy', 'url' => '#'],
        ['title' => 'Tribe', 'accent' => 'Life', 'label' => '5 episodes', 'style' => 'gold', 'url' => '#'],
        ['title' => 'Kingdom', 'accent' => 'Builders', 'label' => '4 episodes', 'style' => 'ice', 'url' => '#'],
    ],

    'upcoming_live' => [
        ['title' => 'Midweek prayer', 'when' => '[Day] · [Time]'],
        ['title' => 'Worship Night', 'when' => 'Fri 6 Nov · 6:00 PM'],
        ['title' => 'Royal Youth Convention', 'when' => 'Sat 14 Nov · 4:00 PM'],
    ],

];

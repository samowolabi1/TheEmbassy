<?php

// Content for the home page: the weekly declaration, announcements, Tribe Market picks and a testimony.

return [

    'declaration' => [
        'week' => '[Date]',
        'lines' => [
            'I am chosen by God and called for a purpose.',
            'I walk in wisdom, favour and excellence this week.',
            'No weapon formed against me shall prosper.',
            'I am a royal priesthood, and I will shine for Christ.',
        ],
    ],

    // `tone` is the tag colour: wash or ice.
    'announcements' => [
        ['tag' => 'This Sunday', 'tone' => 'wash', 'title' => 'Thanksgiving service and family lunch', 'text' => 'Wear white and gold. Lunch is served after service.', 'url' => '#'],
        ['tag' => 'Units', 'tone' => 'ice', 'title' => 'Choir and media auditions are open', 'text' => 'Sign up in the app before 20 November.', 'url' => '#'],
        ['tag' => 'Prayer', 'tone' => 'wash', 'title' => 'Midweek prayer moves online', 'text' => 'Join on YouTube Live every [Day] at [Time].', 'url' => '#'],
    ],

    'market' => [
        ['category' => 'Fashion', 'name' => 'Royal Tribe tee', 'seller' => 'Threadz by Dami', 'price' => '₦9,500', 'swatch' => 'ice', 'url' => '#'],
        ['category' => 'Food', 'name' => 'Party jollof tray', 'seller' => "Kemi's Kitchen", 'price' => '₦25,000', 'swatch' => 'wash', 'url' => '#'],
        ['category' => 'Services', 'name' => 'Birthday shoot', 'seller' => 'Lens & Light Studio', 'price' => '₦40,000', 'swatch' => 'ice', 'url' => '#'],
        ['category' => 'Beauty', 'name' => 'Shea glow butter', 'seller' => 'Glow by Ife', 'price' => '₦6,000', 'swatch' => 'wash', 'url' => '#'],
    ],

    'testimony' => [
        'quote' => 'I sent a prayer request the week of my finals. The Tribe prayed me through, and now I serve in the media unit.',
        'name' => '[Member name]',
        'initials' => 'AO',
        'role' => 'Testimony · Media unit',
    ],

];

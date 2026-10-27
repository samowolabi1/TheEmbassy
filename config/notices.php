<?php

// The paid notice board, where other churches and businesses advertise to this church's members.
// Prices are in naira; change them per deployment.

return [

    'packages' => [
        'week' => ['label' => '1 week', 'days' => 7, 'price' => (int) env('NOTICE_PRICE_WEEK', 5000)],
        'fortnight' => ['label' => '2 weeks', 'days' => 14, 'price' => (int) env('NOTICE_PRICE_FORTNIGHT', 8000)],
        'month' => ['label' => '1 month', 'days' => 30, 'price' => (int) env('NOTICE_PRICE_MONTH', 15000)],
    ],

    // Added to the package price: shown first on the board and on the home page.
    'featured_price' => (int) env('NOTICE_PRICE_FEATURED', 5000),

    'categories' => ['Church event', 'Business', 'Job or internship', 'Service', 'Training or course', 'Other'],

];

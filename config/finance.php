<?php

// Giving funds, and the starting categories for income and expenses. Funds are created from these
// the first time the finance pages open; after that the church manages them in admin.

return [

    'funds' => [
        // Tithes and offerings are received by bank transfer only.
        ['name' => 'Tithe', 'online' => false],
        ['name' => 'Offering', 'online' => false],
        ['name' => 'Building fund', 'description' => 'A youth sanctuary with space for worship, study and community.'],
        ['name' => 'Missions & welfare'],
    ],

    // Money received outside online giving. Tithes stay in Giving, which only the pastor in charge sees.
    'income_categories' => [
        'Sunday offering',
        'Midweek offering',
        'Thanksgiving',
        'Special seed & pledges',
        'Donations',
        'Event income',
        'Sales (books, media, merchandise)',
        'Hall & equipment hire',
        'Welfare contributions',
        'Other',
    ],

    'expense_categories' => [
        'Venue & utilities',
        'Sound & media',
        'Outreach & welfare',
        'Welfare support',
        'Events',
        'Hospitality',
        'Transport',
        'Admin & supplies',
        'Other',
    ],

    // Online gifts, in naira.
    'min_gift' => 100,
    'max_gift' => 10000000,

];

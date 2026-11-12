<?php

// Giving funds and expense categories. Funds are created from these
// the first time the finance pages open; after that the church manages them in admin.

return [

    'funds' => [
        // Tithes and offerings are received by bank transfer only.
        ['name' => 'Tithe', 'online' => false],
        ['name' => 'Offering', 'online' => false],
        ['name' => 'Building fund', 'description' => 'A youth sanctuary with space for worship, study and community.'],
        ['name' => 'Missions & welfare'],
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

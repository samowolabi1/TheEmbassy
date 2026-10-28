<?php

// Giving funds, token plans and expense categories. Funds and token plans are created from these
// the first time the finance pages open; after that the church manages them in admin.

return [

    'funds' => [
        ['name' => 'Tithe'],
        ['name' => 'Offering'],
        ['name' => 'Building fund', 'description' => 'A youth sanctuary with space for worship, study and community.'],
        ['name' => 'Missions & welfare'],
    ],

    'token_plans' => [
        ['name' => 'Standard member', 'amount' => 200000, 'description' => 'Monthly', 'is_default' => true],
        ['name' => 'Student', 'amount' => 100000, 'description' => 'Monthly'],
        ['name' => 'Exempt', 'amount' => 0, 'description' => 'Approved by the pastor'],
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

<?php

// Welfare categories and how support is given.

return [

    'categories' => [
        'financial' => 'Financial hardship',
        'medical' => 'Medical or hospital',
        'bereavement' => 'Bereavement',
        'food' => 'Food and essentials',
        'housing' => 'Rent and housing',
        'education' => 'School fees and education',
        'job' => 'Job loss and work',
        'celebration' => 'Wedding or new baby',
        'other' => 'Something else',
    ],

    'statuses' => [
        'new' => 'New',
        'in_review' => 'Being looked into',
        'awaiting_pastor' => 'Waiting for a pastor',
        'approved' => 'Approved',
        'declined' => 'Declined',
        'supported' => 'Support given',
        'closed' => 'Closed',
    ],

    'note_kinds' => [
        'note' => 'Note',
        'call' => 'Phone call',
        'visit' => 'Visit',
        'support' => 'Support given (not money)',
    ],

    'payment_methods' => [
        'transfer' => 'Bank transfer',
        'cash' => 'Cash',
        'paid_direct' => 'Paid directly (hospital, school, landlord)',
        'items' => 'Items bought',
    ],

];

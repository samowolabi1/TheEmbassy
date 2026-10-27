<?php

// The member marketplace. Buyers pay sellers directly; sellers pay the church a subscription
// to be listed (plan prices and coupons are set in the admin).

return [

    'categories' => ['Fashion', 'Food', 'Beauty', 'Services', 'Books & media', 'Crafts & gifts', 'Other'],

    // The subscription periods offered to sellers. Prices are set by an admin.
    'plans' => [
        'monthly' => ['name' => 'Monthly', 'months' => 1],
        'quarterly' => ['name' => 'Quarterly', 'months' => 3],
        'half_yearly' => ['name' => '6 months', 'months' => 6],
        'yearly' => ['name' => '1 year', 'months' => 12],
        'three_years' => ['name' => '3 years', 'months' => 36],
    ],

    // Discounts an admin can give with a coupon.
    'coupon_percents' => [10, 20, 50, 100],

];

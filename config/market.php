<?php

// The member marketplace. The commission can also be changed by an admin in the dashboard
// (Tribe Market › Settings); this is the starting value.

return [

    // The church's percentage of each order, taken by Paystack at the moment of payment.
    'commission' => (float) env('MARKET_COMMISSION', 5),

    'categories' => ['Fashion', 'Food', 'Beauty', 'Services', 'Books & media', 'Crafts & gifts', 'Other'],

    // Most of one product a buyer can order at once.
    'max_quantity' => 20,

];

<?php

// Donaitech's share of what this deployment takes online, as a percentage of each payment.
// It is deducted by Paystack at the moment of payment (the rest settles to the church's subaccount),
// so nothing is paid out by hand. 0 means the church keeps everything.

return [

    'fees' => [
        'notices' => (float) env('PLATFORM_FEE_NOTICES', 0),
        'tickets' => (float) env('PLATFORM_FEE_TICKETS', 0),
        'market' => (float) env('PLATFORM_FEE_MARKET', 0), // seller subscriptions
        'giving' => (float) env('PLATFORM_FEE_GIVING', 0),
        'tokens' => (float) env('PLATFORM_FEE_TOKENS', 0),
    ],

];

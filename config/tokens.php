<?php

// Tokens: the app's own currency. The pastor in charge (super admin) creates them into the church
// treasury; members earn them from the treasury by taking part, spend them in the market, on event
// tickets and shop subscriptions (which return them to the treasury), and can send them to each other.
// Nobody buys tokens with money, and tokens can't be cashed out.
// Only the pastor in charge changes these, on admin › Treasury.

return [

    'enabled' => true,

    // What a token is worth (naira per token), for subscriptions, tickets and showing values.
    'naira_per_token' => 2,

    // Members sending tokens to each other.
    'transfers' => [
        'enabled' => true,
        'daily_limit' => 1000,
    ],

    // How many tokens each activity earns. Each one is given once per occasion (per service,
    // meeting, quiz, day or week), so refreshing or tapping twice never adds more.
    'rules' => [
        'attend_in_person' => 10,
        'attend_online' => 5,
        'served' => 5,
        'sunday_streak' => 20,
        'unit_meeting' => 5,
        'quiz_play' => 5,
        'quiz_correct' => 1,
        'community_day' => 1,
        'declaration' => 2,
        'profile' => 10,
        'welcome' => 20,
        'birthday' => 10,
        'testimony' => 5,
        'book_returned' => 5,
        'book_review' => 2,
    ],

];

<?php

// Tokens: free rewards members earn by taking part, and spend in the market (where the seller
// accepts them) or on a shop subscription. Nobody pays money for tokens.
// The amounts can be changed in admin › Settings & roles › Tokens.

return [

    'enabled' => true,

    // What a token is worth when paying for a shop subscription (naira per token).
    'naira_per_token' => 10,

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
    ],

];

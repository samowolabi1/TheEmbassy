<?php

// Events calendar. `month` groups events into the tabs on the Events page.
// Day names are copied from the design; check them against the real calendar before launch.

return [

    'featured' => [
        'title' => 'Royal Youth Convention',
        'date_label' => 'Sat 14 November',
        // Used for the countdown.
        'starts_at' => '2026-11-14 16:00:00',
        'timezone' => 'Africa/Lagos',
        'summary' => 'Worship, honest conversations and the biggest Tribe hangout of the year.',
        'where' => 'Main sanctuary, 4:00 PM.',
        'speakers' => '[#]',
        'entry' => '₦[0]',
        'ticket_url' => '#',
        'calendar_url' => '#',
    ],

    'months' => [
        'nov' => 'November',
        'dec' => 'December',
    ],

    'events' => [
        ['month' => 'nov', 'mon' => 'NOV', 'day' => '07', 'tag' => 'Worship', 'title' => 'Worship Night', 'when' => 'Fri · 6:00 PM', 'where' => 'Main sanctuary', 'cta' => 'RSVP', 'url' => '#', 'calendar_url' => '#'],
        ['month' => 'nov', 'mon' => 'NOV', 'day' => '14', 'tag' => 'Convention', 'title' => 'Royal Youth Convention', 'when' => 'Sat · 4:00 PM', 'where' => 'Main sanctuary', 'cta' => 'Get ticket', 'url' => '#', 'calendar_url' => '#', 'highlight' => true],
        ['month' => 'nov', 'mon' => 'NOV', 'day' => '22', 'tag' => 'Career', 'title' => 'Career & Purpose Clinic', 'when' => 'Sat · 11:00 AM', 'where' => 'Hall B', 'cta' => 'Register', 'url' => '#', 'calendar_url' => '#'],
        ['month' => 'nov', 'mon' => 'NOV', 'day' => '30', 'tag' => 'Thanksgiving', 'title' => 'Thanksgiving Sunday', 'when' => 'Sun · [Time]', 'where' => 'Main sanctuary', 'cta' => 'RSVP', 'url' => '#', 'calendar_url' => '#'],
        ['month' => 'dec', 'mon' => 'DEC', 'day' => '05', 'tag' => 'Outreach', 'title' => 'Community Outreach', 'when' => 'Sat · 9:00 AM', 'where' => '[Location]', 'cta' => 'Volunteer', 'url' => '#', 'calendar_url' => '#'],
        ['month' => 'dec', 'mon' => 'DEC', 'day' => '20', 'tag' => 'Carols', 'title' => 'Carols by Candlelight', 'when' => 'Sat · 6:00 PM', 'where' => 'Main sanctuary', 'cta' => 'RSVP', 'url' => '#', 'calendar_url' => '#'],
    ],

];

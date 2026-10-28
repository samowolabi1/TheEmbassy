<?php

// Church staff roles and the admin areas each can open. Administrators can do everything,
// including Settings & roles. Anyone without a role is a member or seller, not staff.

return [

    'areas' => [
        'members' => 'Members',
        'attendance' => 'Attendance & first-timers',
        'care' => 'Follow-up & care',
        'welfare' => 'Welfare (cases and approvals above the HOD\'s limit)',
        'units' => 'Units & fellowships',
        'subscribers' => 'Newsletter subscribers',
        'finance' => 'Giving & tokens',
        'expenses' => 'Expenses & budgets',
        'market' => 'Market',
        'content' => 'Content: announcements, blog and pages',
        'gallery' => 'Photo gallery',
        'notices' => 'Notice board',
        'sermons' => 'Sermons & live',
        'events' => 'Events & tickets',
        'prayer' => 'Prayer requests',
        'messages' => 'Contact messages',
        'settings' => 'Settings & roles',
    ],

    'roles' => [
        'admin' => ['name' => 'Administrator', 'areas' => ['*']],
        'pastor' => ['name' => 'Pastor', 'areas' => ['members', 'attendance', 'care', 'welfare', 'units', 'subscribers', 'finance', 'expenses', 'market', 'content', 'gallery', 'notices', 'sermons', 'events', 'prayer', 'messages']],
        'finance' => ['name' => 'Finance', 'areas' => ['finance', 'expenses', 'market']],
        'secretary' => ['name' => 'Church office', 'areas' => ['members', 'attendance', 'care', 'units', 'subscribers', 'market', 'content', 'gallery', 'notices', 'events', 'prayer', 'messages']],
        'media' => ['name' => 'Media', 'areas' => ['content', 'gallery', 'notices', 'sermons', 'events']],
        'usher' => ['name' => 'Ushering', 'areas' => ['attendance', 'events']],
    ],

];

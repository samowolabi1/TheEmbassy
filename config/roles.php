<?php

// Church staff roles and the admin areas each can open. The pastor in charge (super admin) can do
// everything, and is the only one who sees giving and controls the token currency (creating tokens,
// giving them out, and what activities earn). Administrators can do everything else, including
// Settings & roles. Anyone without a role is a member or seller, not staff.

return [

    'areas' => [
        'members' => 'Members',
        'attendance' => 'Attendance & first-timers',
        'care' => 'Follow-up & care',
        'welfare' => 'Welfare (cases and approvals above the HOD\'s limit)',
        'units' => 'Units & fellowships',
        'subscribers' => 'Weekly email & subscribers',
        'finance' => 'Giving, funds & bank accounts',
        'expenses' => 'Expenses & budgets',
        'market' => 'Market',
        'content' => 'Content: announcements, blog and pages',
        'gallery' => 'Photo gallery',
        'tokens' => 'Tokens: see the treasury and members\' tokens (only the pastor in charge creates or moves them)',
        'community' => 'Community: moderate chat, rooms and quizzes',
        'notices' => 'Notice board',
        'sermons' => 'Sermons & live',
        'events' => 'Events & tickets',
        'prayer' => 'Prayer requests',
        'appointments' => 'Visits with a pastor: hours and bookings',
        'testimonies' => 'Testimonies: read and publish',
        'messages' => 'Contact messages',
        'settings' => 'Settings & roles',
    ],

    'roles' => [
        'super' => ['name' => 'Pastor in charge (super admin)', 'areas' => ['*']],
        'admin' => ['name' => 'Administrator', 'areas' => ['*']],
        'pastor' => ['name' => 'Pastor', 'areas' => ['members', 'attendance', 'care', 'welfare', 'units', 'subscribers', 'finance', 'expenses', 'market', 'content', 'gallery', 'community', 'tokens', 'notices', 'sermons', 'events', 'prayer', 'messages', 'appointments', 'testimonies']],
        'finance' => ['name' => 'Finance', 'areas' => ['finance', 'expenses', 'market']],
        'secretary' => ['name' => 'Church office', 'areas' => ['members', 'attendance', 'care', 'units', 'subscribers', 'market', 'content', 'gallery', 'community', 'tokens', 'notices', 'events', 'prayer', 'messages', 'appointments', 'testimonies']],
        'media' => ['name' => 'Media', 'areas' => ['content', 'gallery', 'community', 'notices', 'sermons', 'events', 'testimonies']],
        'usher' => ['name' => 'Ushering', 'areas' => ['attendance', 'events']],
    ],

];

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
        'expenses' => 'Income, expenses & budgets',
        'market' => 'Market',
        'content' => 'Website content: announcements, the home page slider, the About page and Bible verses',
        'blog' => 'Blog: write, edit and publish articles',
        'gallery' => 'Photo gallery',
        'tokens' => 'Rewards currency: see the treasury and members\' balances (only the pastor in charge creates or moves them)',
        'community' => 'Community: moderate chat, rooms and quizzes',
        'notices' => 'Notice board',
        'sermons' => 'Sermons & live',
        'events' => 'Events & tickets',
        'prayer' => 'Prayer requests',
        'appointments' => 'Visits with a pastor: hours and bookings',
        'permissions' => 'Workers\' permission: see and answer workers who will be away or late',
        'testimonies' => 'Testimonies: read and publish',
        'feedback' => 'Feedback on services, and members\' suggestions',
        'library' => 'Library: books, lending and returns',
        'empowerment' => 'Youth empowerment: programmes, the application form and applications',
        'messages' => 'Contact messages',
        'outreach' => 'Message centre: contacts, and messages to members, sellers and staff',
        'reminders' => 'Reminders & greetings: service reminders, thank-yous, feedback requests and market renewals',
        'settings' => 'Settings & roles',
    ],

    'roles' => [
        'super' => ['name' => 'Pastor in charge (super admin)', 'areas' => ['*']],
        'admin' => ['name' => 'Administrator', 'areas' => ['*']],
        'pastor' => ['name' => 'Pastor', 'areas' => ['members', 'attendance', 'care', 'welfare', 'units', 'subscribers', 'finance', 'expenses', 'market', 'content', 'blog', 'gallery', 'community', 'tokens', 'notices', 'sermons', 'events', 'prayer', 'messages', 'appointments', 'testimonies', 'feedback', 'library', 'reminders', 'outreach', 'empowerment', 'permissions']],
        'finance' => ['name' => 'Finance', 'areas' => ['finance', 'expenses', 'market']],
        'secretary' => ['name' => 'Church office', 'areas' => ['members', 'attendance', 'care', 'units', 'subscribers', 'market', 'content', 'blog', 'gallery', 'community', 'tokens', 'notices', 'events', 'prayer', 'messages', 'appointments', 'testimonies', 'feedback', 'library', 'reminders', 'outreach', 'empowerment']],
        'media' => ['name' => 'Media', 'areas' => ['content', 'blog', 'gallery', 'community', 'notices', 'sermons', 'events', 'testimonies']],
        'usher' => ['name' => 'Ushering', 'areas' => ['attendance', 'events']],
        'librarian' => ['name' => 'Librarian', 'areas' => ['library']],
        'web' => ['name' => 'Website manager', 'areas' => ['content', 'blog', 'gallery']],
        'writer' => ['name' => 'Blog writer', 'areas' => ['blog']],
    ],

];

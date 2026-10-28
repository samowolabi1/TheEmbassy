<?php

// Blog topics, and the design's starter articles. The articles are copied into the database the first time
// migrations run (see App\Services\Content\LegacyContent); after that, write and edit them in admin under Blog.
// Body blocks: ['p', text], ['h2', text], ['quote', text, cite], ['reflect', title, [items]].

$author = [
    'author' => '[Author name]',
    'author_initials' => 'PS',
    'author_bio' => '[Short author bio, one or two lines.]',
    'date' => '[Date]',
];

return [

    'topics' => ['Faith', 'Relationships', 'Career & money', 'Campus life', 'Mental wellness', 'Purpose'],

    'posts' => [
        [
            'slug' => 'living-like-royalty-when-life-feels-ordinary',
            'topic' => 'Purpose',
            'title' => 'Living like royalty when life feels ordinary',
            'dek' => 'Your identity in Christ is not on hold until something big happens. It shapes your Monday morning too.',
            'read' => '7 min read',
            'swatch' => 'mid',
            'featured' => true,
            ...$author,
            'body' => [
                ['p', 'Most of life is not a stage moment. It is lectures, traffic, spreadsheets, laundry and waiting for replies. It is easy to think being "royal" only shows up at a convention or on a Sunday when the worship is loud.'],
                ['p', 'But Scripture calls us a royal priesthood right now, not someday. That identity is not a feeling we wait for. It is a fact that shapes how we show up on the most ordinary Tuesday.'],
                ['h2', 'Royalty shows in how you work'],
                ['p', 'Royals represent a kingdom wherever they go. When you hand in an assignment, serve a customer or write code, you carry the name of the King. Excellence is not pride. It is worship.'],
                ['quote', 'And whatsoever ye do, do it heartily, as to the Lord, and not unto men.', 'Colossians 3:23 (KJV)'],
                ['h2', 'Royalty shows in how you treat people'],
                ['p', 'The way you speak to the bus conductor, your younger sibling or the new person at church says more about your crown than any title. Kindness is a royal habit.'],
                ['h2', 'Royalty shows in how you wait'],
                ['p', 'Waiting for admission, a job or a spouse can make you feel forgotten. A royal waits with confidence, because the King has not changed His mind about you.'],
                ['reflect', 'Reflect this week', [
                    'Where do I feel most "ordinary" right now?',
                    'What would excellence look like there this week?',
                    'Who can I show royal kindness to before Sunday?',
                ]],
            ],
        ],
        ['slug' => 'dating-with-purpose', 'topic' => 'Relationships', 'title' => 'Dating with purpose: five honest questions to ask', 'dek' => 'Before you say yes, ask what you are building together.', 'read' => '6 min read', 'swatch' => 'ice', ...$author],
        ['slug' => 'your-first-salary', 'topic' => 'Career & money', 'title' => 'Your first salary: tithing, saving and side hustles', 'dek' => 'A simple plan for the money you have been praying for.', 'read' => '8 min read', 'swatch' => 'wash', ...$author],
        ['slug' => 'when-faith-and-anxiety-sit-in-the-same-room', 'topic' => 'Mental wellness', 'title' => 'When faith and anxiety sit in the same room', 'dek' => 'Struggling does not mean your faith is weak.', 'read' => '5 min read', 'swatch' => 'navy', ...$author],
        ['slug' => 'staying-on-fire-in-your-final-year', 'topic' => 'Campus life', 'title' => 'Staying on fire for God in your final year', 'dek' => 'Exams, projects and a quiet time that actually happens.', 'read' => '4 min read', 'swatch' => 'gold', ...$author],
        ['slug' => 'how-to-read-the-bible-when-you-are-busy', 'topic' => 'Faith', 'title' => 'How to read the Bible when you are busy', 'dek' => 'Short, daily and honest beats long and rare.', 'read' => '5 min read', 'swatch' => 'mid', ...$author],
        ['slug' => 'you-do-not-need-a-title-to-lead', 'topic' => 'Purpose', 'title' => 'You do not need a title to lead', 'dek' => 'Leadership starts in the unit, the office and at home.', 'read' => '6 min read', 'swatch' => 'ice', ...$author],
    ],

];

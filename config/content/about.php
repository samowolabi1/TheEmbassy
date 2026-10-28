<?php

// Starting content for the About page. Staff edit all of it in admin (Pages), which saves over these defaults.

return [

    'hero_title' => 'A royal tribe, raising a generation for Christ.',
    'hero_text' => ':church is a youth parish of The Redeemed Christian Church of God, built for students, creatives and young professionals.',

    'story_heading' => 'Church that feels like family.',
    'story' => "[Your story: when the parish started, who started it and why. Two or three short paragraphs work best.]\n\nToday we gather every Sunday to worship, learn the Word and build friendships that last beyond the service. Whether you are in school, starting a career or building a business, there is a place for you here.",
    'story_photo' => null,

    'mission' => 'To help young people know Christ, grow in Him and live out their royal calling.',
    'vision' => 'A generation of believers who lead with excellence in every sphere of life.',

    'values' => ['Worship', 'The Word', 'Family', 'Purpose', 'Service'],

    'leaders' => [
        ['name' => "[Pastor's name]", 'role' => 'Pastor in Charge', 'swatch' => 'navy', 'photo' => null],
        ['name' => '[Name]', 'role' => 'Assistant Pastor', 'swatch' => 'gold', 'photo' => null],
        ['name' => '[Name]', 'role' => 'Youth Pastor', 'swatch' => 'ice', 'photo' => null],
        ['name' => '[Name]', 'role' => 'Coordinator, Units', 'swatch' => 'mid', 'photo' => null],
    ],

    'beliefs' => [
        'The Bible is the inspired Word of God.',
        'One God: Father, Son and Holy Spirit.',
        'Salvation is by grace through faith in Jesus.',
        'A life of holiness, empowered by the Holy Spirit.',
        'Water baptism and Holy Communion.',
        'The return of Jesus Christ.',
    ],

    // Shown until units in admin have descriptions.
    'units' => [
        ['name' => 'Choir', 'text' => 'Lead the Tribe in worship every Sunday and at special services.'],
        ['name' => 'Media & tech', 'text' => 'Livestream, sound, lights, photography and social media.'],
        ['name' => 'Ushering & welcome', 'text' => 'Be the first smile people see and help first-timers feel at home.'],
        ['name' => 'Drama & creative arts', 'text' => 'Tell the gospel through drama, spoken word, dance and design.'],
        ['name' => 'Prayer', 'text' => 'Stand in the gap for the church and pray over every request.'],
        ['name' => 'Outreach & welfare', 'text' => 'Take the love of Christ to campuses, streets and families in need.'],
    ],

    'faqs' => [
        ['q' => 'What should I wear?', 'a' => 'Come as you are. Most people dress smart casual, and nobody will judge your outfit.'],
        ['q' => 'How long is the service?', 'a' => 'Sunday service runs for about :length. Stay after for refreshments and to meet the Tribe.'],
        ['q' => 'Is there parking?', 'a' => ':parking. Our ushers will guide you when you arrive.'],
        ['q' => 'I am new to church. Will I fit in?', 'a' => 'Yes. Many of us came for the first time not knowing anyone. Our welcome team will introduce you around.'],
        ['q' => 'Can I watch online first?', 'a' => 'Absolutely. We stream every Sunday on YouTube and in the Royal Tribe app.'],
    ],

];

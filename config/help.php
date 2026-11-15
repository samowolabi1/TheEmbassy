<?php

// Help for the admin: what each page is for, common questions, guided tours and step-by-step
// guides. Shown in the Help panel on every admin page and on admin › Help centre.
//
// pages: keyed by the sidebar item (the layout's `active`). `area` limits who sees it in the
// help centre (none = every staff member).
// tours: keyed by route name. Each step points at something on the page: a CSS selector, or
// "box:Heading" for the box whose heading starts with that text. Steps whose target isn't on
// the page are skipped, so a tour still works for people who see less.
// guides: common jobs, step by step, each with the page it starts on.

return [

    'pages' => [

        'dashboard' => [
            'title' => 'Dashboard',
            'about' => 'Your starting point: this month\'s numbers, what needs your attention and what\'s coming up. You only see the parts your role covers.',
            'faqs' => [
                ['Why don\'t I see giving on the dashboard?', 'Giving is private to the pastor in charge (super admin). Other staff never see giving records or totals.'],
                ['What does "Needs your attention" show?', 'Things waiting for someone in your role: members to approve, follow-ups that are overdue, expenses to approve, prayer requests to read and so on. Each one links straight to the page that handles it.'],
                ['Why is a menu item missing for me?', 'Each role only sees its own areas. An administrator can change roles in Settings & roles.'],
            ],
        ],

        'members' => [
            'title' => 'Members',
            'area' => 'members',
            'about' => 'The church register. Approve people who joined online, keep profiles up to date, and see each member\'s attendance, milestones and follow-ups.',
            'faqs' => [
                ['Someone joined online. Why can\'t they use the app yet?', 'New sign-ups wait under "Waiting for approval" until someone approves them. Once approved they can use the community, the library, check in and earn rewards.'],
                ['Can I add members without them signing up?', 'Yes: "Add member" adds someone to the register without a login, or import many at once from a spreadsheet (CSV). They can still create a login later with the same email.'],
                ['What does "Inactive" mean?', 'Someone who has moved away or stopped coming. They stay on the register with their history but drop out of counts and follow-ups.'],
                ['How do I download the register?', 'Use Export: it gives a spreadsheet of the members you\'re looking at, with the filters you\'ve chosen.'],
                ['What are workers and ministers?', 'Workers serve in a unit; the unit\'s head makes them a worker from the unit\'s Members page. Ministers work closely with the pastors; only a pastor can make a worker a minister, from the member\'s profile. Filter the list by role, and reach them on their own in the Message centre.'],
            ],
        ],

        'firsttimers' => [
            'title' => 'First-timers',
            'area' => 'attendance',
            'about' => 'Cards from visitors, filled in at /welcome (put the QR code at the door) or typed in by staff after a service.',
            'faqs' => [
                ['How do visitors fill in a card?', 'Print the QR code for the /welcome page and put it at the door or on the screen. Cards land here straight away.'],
                ['What happens after a card comes in?', 'A follow-up is created on Follow-up & care so someone calls them within a few days. Mark each one as called with a note of how it went.'],
                ['A visitor has joined the church. What now?', 'Add them to the register from their card, so their details carry over.'],
            ],
        ],

        'care' => [
            'title' => 'Follow-up & care',
            'area' => 'care',
            'about' => 'The care team\'s to-do list: calling first-timers, welcoming new members and checking on anyone who has missed several Sundays in a row.',
            'faqs' => [
                ['Where do follow-ups come from?', 'Most are made automatically: first-timer cards, newly approved members, and members who miss Sundays in a row (the number is set in Settings & roles). You can also add one by hand.'],
                ['Someone came back to church. Do I close their follow-up?', 'No need: when someone who was missed checks in again, their follow-up closes itself.'],
                ['How do I share the work?', 'Assign each follow-up to someone on the team. They get an email, and it shows under their name.'],
            ],
        ],

        'attendance' => [
            'title' => 'Attendance',
            'area' => 'attendance',
            'about' => 'Schedule services so members can check themselves in at church or online, show the check-in code on the screen, and record headcounts.',
            'faqs' => [
                ['How do members check in at church?', 'They open /check-in on their phone (or scan the QR code on the screen) during the check-in window and enter the code shown on the church screen. Their location is checked too, if that\'s set up in Settings & roles.'],
                ['Someone couldn\'t check in. Can I mark them present?', 'Yes: open the service and tick them under "Mark members present".'],
                ['What\'s the headcount for?', 'The total number of people in the room, members or not, and the number online. It\'s separate from members who checked in.'],
                ['Do services repeat?', 'When adding a service you can make it repeat weekly, so Sundays and midweek services are always there.'],
            ],
        ],

        'welfare' => [
            'title' => 'Welfare',
            'area' => 'welfare',
            'about' => 'Welfare cases and packages, run by the welfare unit. Cases are confidential: only the welfare leaders and staff with the welfare area see them.',
            'faqs' => [
                ['Who approves welfare support?', 'The welfare head approves amounts up to the limit set in Settings & roles; larger amounts go to the pastor.'],
                ['How do members ask for help?', 'From Welfare in their account, or the welfare unit opens a case for them.'],
            ],
        ],

        'units' => [
            'title' => 'Units & fellowships',
            'area' => 'units',
            'about' => 'The service units members serve in and the house fellowships they meet in during the week. Members choose from these when they join.',
            'faqs' => [
                ['How do I make someone head of a unit?', 'Pick a head and an assistant for the unit. They then get the unit leaders\' area for their unit: members, meetings, rota and messages.'],
                ['Can I rename or remove a unit?', 'Yes. Renaming keeps its members. Remove only units nobody belongs to.'],
                ['Who can make someone a worker?', 'The unit\'s head or assistant (and staff who manage units) can make a unit member a worker, or a member again. A worker who leaves the unit becomes a member again. Making someone a minister is for pastors.'],
            ],
        ],

        'subscribers' => [
            'title' => 'Weekly email',
            'area' => 'subscribers',
            'about' => 'The email that goes out every Monday morning with the week\'s declaration, announcements, new messages and articles, and events.',
            'faqs' => [
                ['When does it go out?', 'Every Monday morning at the time set in Settings & roles. It\'s put together automatically from what\'s on the site.'],
                ['How do people sign up or leave?', 'Anyone can sign up at the bottom of the website. Every email has a one-click unsubscribe link.'],
            ],
        ],

        'finance' => [
            'title' => 'Giving',
            'area' => 'finance',
            'about' => 'Online gifts and cash offerings recorded by the pastor in charge, by fund, with the church\'s bank accounts. Private to the pastor in charge.',
            'faqs' => [
                ['Why can only the pastor in charge see this?', 'Giving is private: who gave what is kept to the pastor in charge (super admin). Other staff record money on "Record income & spending", which doesn\'t show who gave.'],
                ['How are tithes paid?', 'Tithes and offerings are by bank transfer only. The Give page shows the accounts; online card giving is for donations to funds.'],
                ['What is a fund?', 'Something people give towards, such as the building fund. A fund can have a target, which shows progress on the Give page.'],
            ],
        ],

        'money' => [
            'title' => 'Record income & spending',
            'about' => 'Every staff member writes down money that came in or went out for the church here. Finance or a pastor checks each entry.',
            'faqs' => [
                ['What should I record as money in?', 'Anything received outside online giving: offerings counted after a service, thanksgiving, pledges, donations, hall hire, sales and so on. Pick the closest category.'],
                ['Do I need a receipt?', 'It\'s optional but helps: a photo of the receipt or the offering count sheet, up to 5 MB.'],
                ['Can I change an entry?', 'Not after sending. If something is wrong, tell finance: they can decline it with a reason, and you record it again.'],
                ['Where do I see what happened to my entry?', 'Under "What you\'ve recorded": being checked, confirmed or approved, or declined with the reason.'],
            ],
        ],

        'income' => [
            'title' => 'Income & summary',
            'area' => 'expenses',
            'about' => 'Confirm the income staff record, see each month\'s money in and out by category, and keep the categories.',
            'faqs' => [
                ['Why can\'t I confirm an entry?', 'Nobody confirms money they recorded themselves, except an administrator. Ask someone else on the finance team.'],
                ['Does the summary include online giving?', 'No. It counts confirmed income and approved expenses only. Online giving is under Giving.'],
                ['What happens if I rename a category?', 'Past entries and budgets move to the new name. Hide a category instead of renaming it if you just stop using it.'],
            ],
        ],

        'expenses' => [
            'title' => 'Expenses & budgets',
            'area' => 'expenses',
            'about' => 'Spending logged with receipts, approved by someone other than the person who logged it, and measured against each category\'s monthly budget.',
            'faqs' => [
                ['Who can approve an expense?', 'Anyone with this area except the person who logged it (administrators excepted).'],
                ['How do budgets work?', 'Set an amount per category for the month. The bar shows approved spending against it and turns red when it goes over. Leave it empty for no budget.'],
                ['Can I download the month?', 'Yes: Export gives a spreadsheet of the month\'s expenses. Income & summary exports money in and out together.'],
            ],
        ],

        'market' => [
            'title' => 'Market',
            'area' => 'market',
            'about' => 'Member shops: approve applications, see subscriptions and payments, set plan prices and make coupons.',
            'faqs' => [
                ['How does a shop get on the market?', 'A member applies at /market/open-a-shop. Approve it here and they\'re emailed a link to set their password and add listings.'],
                ['Why has a shop disappeared from the market?', 'Its subscription has lapsed. It comes back as soon as the seller renews.'],
                ['How do coupons work?', 'A coupon takes a percentage off a subscription. You can limit how many times it\'s used.'],
            ],
        ],

        'content' => [
            'title' => 'Content & announcements',
            'area' => 'content',
            'about' => 'Announcements on the website and in the app, and the weekly declaration members say from their accounts.',
            'faqs' => [
                ['How do I send an announcement to members\' phones?', 'Publish it, then use "Send" on it: you can email members, send it on WhatsApp, notify everyone who installed the app, or share it to a WhatsApp group.'],
                ['Can I schedule an announcement?', 'Yes: choose "Schedule" and pick when it should appear.'],
                ['What is the weekly declaration?', 'A short confession for the week. Members say it from their account (and earn rewards for it), and it goes in the Monday email.'],
            ],
        ],

        'blog' => [
            'title' => 'Blog',
            'area' => 'content',
            'about' => 'Articles for the Blog page and the home page: write, preview, schedule and publish.',
            'faqs' => [
                ['How do I format an article?', 'Use the toolbar for headings, bold, links, lists, scripture cards and the "reflect" box, and add pictures straight into the text.'],
                ['Can I publish later?', 'Yes: save it with a date in the future and it goes live then. Drafts stay hidden until published.'],
                ['I changed an article\'s address. Will old links break?', 'No. The old address keeps working and sends readers to the new one.'],
            ],
        ],

        'pages' => [
            'title' => 'Pages',
            'area' => 'content',
            'about' => 'The words and pictures on the About page and the home page, including the home page slider.',
            'faqs' => [
                ['How do I change the slider on the home page?', 'Open the Home tab, then Slider. Each slide has words, up to two buttons and a picture or colour. Change the Position numbers to reorder them.'],
                ['What size should pictures be?', 'Wide pictures (about 3:2) work best for slides. They\'re resized for phones automatically.'],
            ],
        ],

        'gallery' => [
            'title' => 'Gallery',
            'area' => 'gallery',
            'about' => 'Albums of photos and videos for the Gallery page.',
            'faqs' => [
                ['How do I add photos?', 'Make an album, open it and upload photos. They\'re resized and location data is removed.'],
                ['Can I add videos?', 'Yes: paste a YouTube link into an album.'],
            ],
        ],

        'tokens' => [
            'title' => 'Reward treasury',
            'area' => 'tokens',
            'about' => 'The church\'s reward currency: members earn it for taking part and spend it in the market, on tickets and subscriptions. Only the pastor in charge creates, gives or takes it.',
            'faqs' => [
                ['Can members buy or cash out the currency?', 'No. It can\'t be bought with money and can\'t be cashed out. It\'s earned from the treasury and spent inside the app.'],
                ['Members aren\'t being paid rewards. Why?', 'The treasury has run out. The pastor in charge creates more on this page.'],
                ['How do I rename it from Crowns?', 'The pastor in charge changes the name and icon under "What earns…" on this page. Every page follows.'],
                ['What does putting a wallet on hold do?', 'The member keeps their balance but can\'t spend or send until it\'s released.'],
            ],
        ],

        'community' => [
            'title' => 'Community',
            'area' => 'community',
            'about' => 'The members\' chat rooms and Bible quizzes. Messages members report land here for a moderator.',
            'faqs' => [
                ['How do I deal with a reported message?', 'Read it in context, then press Remove, Remove and mute, or Keep it if it\'s fine.'],
                ['What does muting do?', 'A muted member can read but can\'t post messages or book reviews until the mute ends.'],
                ['How do quizzes work?', 'Write the questions, set when it opens and closes and the seconds per question. Members get one go each; answers show after it closes.'],
            ],
        ],

        'notices' => [
            'title' => 'Notice board',
            'area' => 'notices',
            'about' => 'Paid notices from other churches and businesses. Nothing appears on the website until it\'s approved here.',
            'faqs' => [
                ['When does a notice go live?', 'Only after you approve it here. Decline with a reason if it isn\'t suitable.'],
            ],
        ],

        'live' => [
            'title' => 'Sermons & live',
            'area' => 'sermons',
            'about' => 'The message library and series on the Watch and home pages, and the live stream player.',
            'faqs' => [
                ['How do I add last Sunday\'s message?', 'Add a message with its YouTube link, speaker, date and series. If YouTube is connected, broadcasts are created and listed automatically.'],
                ['How does the Watch page know we\'re live?', 'When YouTube is connected, the page goes live within a minute of the stream starting and shows the replay afterwards.'],
            ],
        ],

        'media-unit' => [
            'title' => 'Media requests & files',
            'area' => 'sermons',
            'about' => 'Requests for flyers, videos and coverage, and the media unit\'s shared files.',
            'faqs' => [],
        ],

        'media-requests' => [
            'title' => 'Ask the media team',
            'about' => 'Ask the media unit for a flyer, video, slide or coverage of an event, and follow its progress.',
            'faqs' => [
                ['How early should I ask?', 'As early as you can, and say when you need it by on the form, so the team can plan around services and events.'],
            ],
        ],

        'events' => [
            'title' => 'Events & tickets',
            'area' => 'events',
            'about' => 'Events with free places or paid tickets, and checking people in at the door with the QR code on their ticket.',
            'faqs' => [
                ['How do I check people in at the door?', 'Scan the QR code on their ticket with a phone, or type the ticket code in the box at the top of Events & tickets and press Find. It shows whether it\'s valid and lets you mark them in.'],
                ['Can an event have more than one ticket type?', 'Yes: for example early bird, regular and VIP, each with its own price, number of places and limit per booking.'],
                ['Can members pay with Crowns?', 'Yes, for paid tickets, at the value set on the treasury page.'],
            ],
        ],

        'appointments' => [
            'title' => 'Visits with a pastor',
            'area' => 'appointments',
            'about' => 'Members book visits from their account at the times set under Visiting hours. Confirm, move or decline each request.',
            'faqs' => [
                ['Where do the times members can book come from?', 'From each pastor\'s weekly visiting hours, minus time off and visits already booked. Set them under Visiting hours.'],
                ['Can the office read the member\'s private note?', 'No. Only the pastor they\'re seeing and the pastor in charge can read it; the office sees that there is a note.'],
            ],
        ],

        'library' => [
            'title' => 'Library',
            'area' => 'library',
            'about' => 'The lending desk and the book catalogue. Members reserve online; hand books over here when they collect them and mark them returned.',
            'faqs' => [
                ['A member is at the desk to collect a book. What do I do?', 'Find them under "To hand over" (search by name, phone or book) and press "Hand over". Their loan starts then.'],
                ['Someone is waiting for a book that was just returned. Do I tell them?', 'No need: when you mark a book returned, the next person waiting is told it\'s ready for them.'],
                ['Can I give someone more time?', 'Yes: "Give more time" adds 1, 2 or 4 weeks, even if someone is waiting.'],
                ['How do I hide a book?', 'Edit it and turn off "Show in the library". Its history stays.'],
            ],
        ],

        'feedback' => [
            'title' => 'Feedback & suggestions',
            'area' => 'feedback',
            'about' => 'What members say about services, and their ideas for the church, sent from their accounts.',
            'faqs' => [
                ['Can members see my answer to a suggestion?', 'Yes: they see the status and your answer in their account, and get an email and phone notification when there\'s a new answer.'],
                ['Who sent a "no name" suggestion?', 'Nobody can see that, by design. Members choose to leave their name off.'],
            ],
        ],

        'testimonies' => [
            'title' => 'Testimonies',
            'area' => 'testimonies',
            'about' => 'Testimonies members share from their accounts. Read, tidy and publish them, or decline with a kind note.',
            'faqs' => [
                ['Will a published testimony go on the website?', 'Only if the member agreed to that when sharing. Otherwise it\'s kept for the church (for example, to read in a service).'],
                ['Does the member earn anything?', 'Yes: publishing gives them a reward once a month at most.'],
            ],
        ],

        'prayer' => [
            'title' => 'Prayer requests',
            'area' => 'prayer',
            'about' => 'Requests from the home page form and from members\' accounts. Private requests are for the pastoral team only.',
            'faqs' => [
                ['How do I encourage someone who asked for prayer?', 'Reply on the request. Members get your reply by email and on their phone.'],
            ],
        ],

        'outreach' => [
            'title' => 'Message centre',
            'area' => 'outreach',
            'about' => 'Send a message to members, sellers, staff or everyone, by email, WhatsApp or app notification, and see everyone you can reach in Contacts.',
            'faqs' => [
                ['How do I message just one unit or fellowship?', 'Choose Members under "Who it\'s for", then pick the unit or house fellowship. You can also narrow it by gender, birthday month, or approval status.'],
                ['Will someone get it twice if they\'re a member and a seller?', 'No. Each person gets each message once.'],
                ['How do I greet people by name?', 'Write {first_name} where the name should go, e.g. "Hi {first_name},". It\'s filled in for each person.'],
                ['Can I check it before it goes out?', 'Yes: "Send me a test" sends it only to you, and the live count shows how many people it will reach.'],
                ['Can I stop a message?', 'Yes, while it\'s scheduled or still going out: open it and press Stop. People who already got it keep it.'],
                ['Who can\'t be reached?', 'People with no email, WhatsApp number or app. The message\'s page lists them under "Couldn\'t reach" so the office can call them.'],
            ],
        ],

        'reminders' => [
            'title' => 'Reminders & greetings',
            'area' => 'reminders',
            'about' => 'Messages the app sends by itself: service reminders, thank-yous to the people who came, feedback requests, and renewal reminders for market sellers.',
            'faqs' => [
                ['Who gets the service reminder?', 'Every approved member, before each service on the Attendance page. Add services there (repeating weekly for Sundays) and the reminders follow.'],
                ['Who gets the thank-you and the feedback request?', 'Only members who were at the service: those who checked in or were marked present. The feedback request skips anyone who has already said how it was.'],
                ['How are they sent?', 'By email (ticked to start), WhatsApp and/or app notification, as you tick for each one, and only while that way is switched on in Settings & roles › Notifications. Members also find them in Messages in the app. WhatsApp is charged by Meta per message and only goes to members who haven\'t turned it off.'],
                ['Can I change the wording?', 'Yes. Words in curly brackets, like {first_name} or {time}, are filled in for each person. "How it reads" shows the result as you type.'],
                ['I turned one on. Will old services get it?', 'No. Only services that are coming up (for reminders) or just ended (for thank-yous and feedback) get them, and each service only once.'],
                ['How do I check it works?', 'Press "Send me a test" on any card. It sends the saved message to you only.'],
            ],
        ],

        'messages' => [
            'title' => 'Contact messages',
            'area' => 'messages',
            'about' => 'Messages sent from the Contact page. Reply by email or phone and mark them done.',
            'faqs' => [
                ['Can I get these by email?', 'Yes: add the church email in Settings & roles and a copy of each message is sent there.'],
            ],
        ],

        'settings' => [
            'title' => 'Settings & roles',
            'area' => 'settings',
            'about' => 'Church details shown on the website, check-in, messaging and other settings, and who can use this dashboard.',
            'faqs' => [
                ['How do I add a staff member?', 'Under Staff, add their email and pick a role. Each role only sees its own areas; the table shows what each role can open.'],
                ['Why can\'t I see the giving or currency settings?', 'Those belong to the pastor in charge (super admin) and are on the Giving and treasury pages.'],
                ['How do members get notifications?', 'By email to start: it\'s the default for every notification. Under Notifications you can also switch on WhatsApp (once the WhatsApp Business details are added; Meta charges per message) and switch app notifications on or off. Text messages aren\'t used.'],
                ['Where do I change the address on the map?', 'Under Church details. The map and "Get directions" follow the address.'],
            ],
        ],

        'help' => [
            'title' => 'Help centre',
            'about' => 'Guides for common jobs and answers to common questions, for the parts of the dashboard you use.',
            'faqs' => [],
        ],
    ],

    'tours' => [

        'admin.dashboard' => [
            ['.side__nav', 'Your menu', 'Everything you can use is here, grouped by kind. You only see the areas your role covers.'],
            ['.stats', 'This month at a glance', 'Key numbers for this month. Tap a tile to open the page behind it.'],
            ['box:Needs your attention', 'Needs your attention', 'Things waiting for someone in your role. Each one links to where you deal with it.'],
            ['[data-help-open]', 'Help is always here', 'Open Help on any page for what it\'s for, common questions and a tour like this one.'],
        ],

        'admin.members.index' => [
            ['.tabs', 'Members by status', 'Approved members, people waiting for approval, and inactive members. New sign-ups appear under "Waiting for approval".'],
            ['.box--flush', 'The register', 'Search and filter by unit or fellowship, then open anyone to see their profile, attendance and follow-ups.'],
            ['.page-head .actions', 'Add, import, export', 'Add one member, import many from a spreadsheet, or export the list you\'re looking at.'],
        ],

        'admin.members.show' => [
            ['box:Profile', 'Their details', 'Contact details, unit, fellowship and milestones. Edit keeps it up to date.'],
            ['box:Attendance', 'Attendance', 'Which of the last Sundays they were at.'],
            ['box:Follow-ups', 'Follow-ups', 'Calls and visits from the care team, and how they went.'],
        ],

        'admin.first-timers.index' => [
            ['.page-head__sub', 'Where cards come from', 'Visitors fill in a card at the /welcome page. Put its QR code at the door.'],
            ['.box--flush', 'The cards', 'Mark each person as called and note how it went, or add them to the register when they join.'],
            ['#new-card', 'Typing in a paper card', 'Add a card here for visitors who filled in a paper one.'],
        ],

        'admin.care.index' => [
            ['.tabs', 'Open and done', 'Follow-ups still to do, and the ones already done with their outcomes.'],
            ['.box--flush', 'Who to call', 'Call or WhatsApp straight from the list, assign it to someone, and record how it went.'],
            ['#new-follow-up', 'Add one yourself', 'Most follow-ups are made automatically, but you can add one for anyone.'],
        ],

        'admin.attendance.index' => [
            ['box:Today and coming up', 'Upcoming services', 'Services members can check in to. Open one to show the check-in code on the screen.'],
            ['box:Add a service', 'Add a service', 'Choose the time, whether people check in at church, online or both, and whether it repeats weekly.'],
            ['box:Past services', 'Past services', 'Open one to see who checked in, mark people present by hand, and record the headcount.'],
        ],

        'admin.attendance.show' => [
            ['.stats', 'This service', 'How many checked in themselves, how many were marked present and the headcount.'],
            ['box:Mark members present', 'Mark people present', 'For anyone who couldn\'t check in on their phone. Filter by unit to find them faster.'],
            ['box:Headcount', 'Headcount', 'Everyone in the room and online, members or not.'],
        ],

        'admin.finance.index' => [
            ['.stats', 'Giving this period', 'Totals for the period you choose, by how people gave.'],
            ['box:Funds', 'Funds', 'What people give towards. A target shows progress on the Give page.'],
            ['#record-gift', 'Record cash offering', 'Write down cash or transfers received so all giving is in one place.'],
        ],

        'admin.money.index' => [
            ['.tabs', 'Money in or out', 'Choose whether you\'re recording money received or money spent.'],
            ['.box.grow form', 'The details', 'What it was, the category, amount, date and how it came in. Add a photo of the receipt if you have one.'],
            ['.side-col', 'What you\'ve recorded', 'Your entries and where they\'re up to: being checked, confirmed, approved or declined with a reason.'],
        ],

        'admin.income.index' => [
            ['.stats', 'The month at a glance', 'Confirmed money in, approved money out, and what\'s left over. Pick another month at the top.'],
            ['box:Money in by category', 'Where it came from', 'Money in and out, each broken down by category.'],
            ['.tabs', 'Entries to confirm', 'Income staff recorded. Confirm it once it\'s counted and banked, or decline with a reason.'],
            ['#categories', 'Categories', 'Add, rename or hide the categories staff choose from.'],
        ],

        'admin.expenses.index' => [
            ['.tabs', 'Awaiting approval', 'Spending staff logged. Someone other than the person who logged it approves or declines it.'],
            ['#new-expense', 'Log an expense', 'Add spending with its category, amount, date and a receipt.'],
            ['.side-col', 'Budgets', 'Set a monthly budget per category. The bar shows approved spending against it.'],
        ],

        'admin.market.index' => [
            ['box:Shop applications', 'Applications', 'Members who want a shop. Approve to email them a link to set up.'],
            ['box:Subscription prices', 'Plan prices', 'What shops pay to be on the market, per plan.'],
            ['box:Coupons', 'Coupons', 'Money off subscriptions, with an optional limit on uses.'],
        ],

        'admin.content' => [
            ['#new-announcement', 'New announcement', 'Write it, see it on the phone preview, then publish now or schedule it.'],
            ['box:All announcements', 'All announcements', 'Edit, unpublish, or "Send" one to members by text, app notification or WhatsApp.'],
            ['#declaration', 'Weekly declaration', 'This week\'s declaration, said by members from their accounts.'],
        ],

        'admin.posts.index' => [
            ['.tabs', 'By status', 'Published, scheduled and draft articles.'],
            ['.page-head .actions', 'Write an article', 'Start a new article. You can preview it before it goes live.'],
        ],

        'admin.pages.edit' => [
            ['.tabs', 'Which page', 'Switch between the About page and the home page.'],
            ['#slider', 'Home page slider', 'The slides at the top of the home page: words, buttons and a picture for each.'],
        ],

        'admin.gallery.index' => [
            ['box:Albums', 'Albums', 'Open an album to add photos and videos or change their order.'],
            ['box:New album', 'New album', 'Make an album for an event or a season.'],
        ],

        'admin.tokens.index' => [
            ['.stats', 'The books', 'What\'s in the treasury, with members, and held for orders. They always add up.'],
            ['#award', 'Give or take', 'Reward a member, or take back what was given by mistake. Pastor in charge only.'],
            ['box:Create or destroy', 'Create or destroy', 'Add new currency to the treasury so rewards keep flowing, or remove unused currency.'],
            ['#settings', 'What earns them', 'The name and icon, its value in naira, and how much each activity earns.'],
        ],

        'admin.community.index' => [
            ['box:Reports', 'Reports', 'Messages members flagged. Remove, remove and mute, or keep it.'],
            ['box:Rooms', 'Rooms', 'The chat rooms, who can post and their order.'],
            ['box:Bible quizzes', 'Bible quizzes', 'Write quizzes and see results.'],
        ],

        'admin.notices.index' => [
            ['.tabs', 'To review', 'Notices waiting for approval, live ones and past ones.'],
            ['.box--flush', 'Each notice', 'Approve to put it on the website, or decline with a reason.'],
        ],

        'admin.sermons.index' => [
            ['box:Live stream', 'Live stream', 'What the Watch page shows when you\'re live.'],
            ['box:Messages', 'Messages', 'The sermon library. Add a message with its YouTube link.'],
            ['box:Series', 'Series', 'Group messages into series.'],
        ],

        'admin.events.index' => [
            ['.tabs', 'Upcoming and past', 'Your events, with places taken.'],
            ['#ticket-code', 'Check a ticket at the door', 'Type the code on someone\'s ticket and press Find to check them in at the door.'],
            ['.page-head .actions', 'New event', 'Make an event with free places or paid ticket types.'],
        ],

        'admin.events.show' => [
            ['.stats', 'Bookings', 'Places taken, money in and how many have arrived.'],
            ['#attendee-search', 'Find someone', 'Search attendees by name, email or ticket code.'],
        ],

        'admin.events.create' => [
            ['box:Details', 'Details', 'Title, date, venue and a short summary.'],
            ['box:Tickets', 'Tickets', 'One or more ticket types: price (0 for free), number of places and limit per booking.'],
        ],

        'admin.appointments.index' => [
            ['.tabs', 'Requests and visits', 'New requests to confirm, coming visits and past ones.'],
            ['#pastor-filter', 'Each pastor', 'See one pastor\'s visits.'],
            ['.page-head .actions', 'Visiting hours', 'Set when each pastor sees members, and their time off.'],
        ],

        'admin.library.desk' => [
            ['.tabs', 'The desk', 'Books to hand over, out on loan, overdue, the waiting list and history.'],
            ['#loan-q', 'Find someone', 'Search by member, phone or book when someone comes to the desk.'],
            ['.box--flush', 'Hand over and take back', 'Hand over a reserved book, mark it returned, or give more time.'],
            ['.page-head .actions', 'The books', 'Add books and edit the catalogue.'],
        ],

        'admin.feedback.index' => [
            ['.tabs', 'Services and suggestions', 'Ratings for recent services, and suggestions members sent.'],
            ['.box--flush', 'Answer suggestions', 'Set where each suggestion is up to and write an answer the member sees.'],
        ],

        'admin.testimonies.index' => [
            ['.tabs', 'To review', 'New testimonies, published ones and declined ones.'],
            ['.box--flush', 'Read and publish', 'Tidy the spelling, then publish or decline with a kind note.'],
        ],

        'admin.prayer-requests.index' => [
            ['.tabs', 'Requests', 'New and read requests.'],
            ['.box--flush', 'Reply', 'Reply to encourage members; they get it by email and on their phone.'],
        ],

        'admin.outreach.index' => [
            ['.page-head .actions', 'Write or find people', 'Start a new message, or open Contacts to see everyone you can reach.'],
            ['.stats', 'Quick start', 'Tap a group to write to them straight away.'],
            ['.box--flush', 'Your messages', 'Drafts, scheduled ones and what went out, with how many people each reached.'],
        ],

        'admin.outreach.create' => [
            ['box:Who it', 'Who it\'s for', 'Members (narrowed by unit, fellowship, gender, birthday month or status), sellers, staff or everyone.'],
            ['[data-reach]', 'How many', 'Counts the people as you change the choices, and how many each way reaches.'],
            ['box:The message', 'The message', 'Write it. {first_name} greets each person by name.'],
            ['box:Send by', 'Send by', 'Email, WhatsApp and/or app notification.'],
            ['box:When', 'When', 'Send now, schedule it, send yourself a test, or save it as a draft.'],
        ],

        'admin.reminders.index' => [
            ['.stats', 'What went out', 'How many people each kind of message reached in the last 30 days.'],
            ['#service', 'Each message', 'Switch it on or off, choose when it goes and how it\'s sent, and write the words. "How it reads" previews it.'],
            ['box:Coming up', 'Coming up', 'The next services and when their reminder goes out.'],
            ['box:Subscriptions ending soon', 'Sellers to remind', 'Shops whose subscription ends in the next two weeks.'],
        ],

        'admin.messages.index' => [
            ['.tabs', 'New and done', 'Messages from the Contact page.'],
        ],

        'admin.settings.edit' => [
            ['box:Staff', 'Staff', 'Who can use this dashboard and their role.'],
            ['box:What each role can open', 'Roles', 'What each role can see and do.'],
            ['.box.form', 'Settings', 'Church details, check-in, messaging and more. Save at the bottom.'],
        ],
    ],

    'guides' => [
        'message-everyone' => [
            'title' => 'Send a message to all members or sellers',
            'area' => 'outreach',
            'route' => 'admin.outreach.create',
            'steps' => [
                'Open Message centre and press New message.',
                'Under "Who it\'s for", choose Members, Sellers, Staff or Everyone. Narrow members by unit, fellowship or birthday month if you like; the count shows how many it reaches.',
                'Write the subject and message. Start with "Hi {first_name}," to greet each person by name.',
                'Tick email (and WhatsApp or app notification if you want), press "Send me a test" to check it, then Send now or Schedule.',
            ],
        ],
        'approve-member' => [
            'title' => 'Approve someone who joined online',
            'area' => 'members',
            'route' => 'admin.members.index',
            'steps' => [
                'Open Members and choose the "Waiting for approval" tab.',
                'Open the person and check their details.',
                'Press Approve. They get the full app straight away, and a welcome follow-up is made for the care team.',
            ],
        ],
        'sunday-checkin' => [
            'title' => 'Run Sunday check-in',
            'area' => 'attendance',
            'route' => 'admin.attendance.index',
            'steps' => [
                'Open Attendance. Sunday\'s service is under "Today and coming up" (add it if it isn\'t, and make it repeat weekly).',
                'Open the service and press "Show code screen". Put it on the projector before the service.',
                'Members open /check-in on their phone (or scan the QR code) and type the code on the screen.',
                'After the service, open it again to tick anyone who couldn\'t check in, and enter the headcount.',
            ],
        ],
        'first-timer' => [
            'title' => 'Follow up a first-timer',
            'area' => 'care',
            'route' => 'admin.care.index',
            'steps' => [
                'Open Follow-up & care. New first-timers are at the top.',
                'Assign it to someone on the team, or take it yourself.',
                'Call or WhatsApp from the list, then record how it went.',
            ],
        ],
        'record-offering' => [
            'title' => 'Record Sunday\'s offering',
            'route' => 'admin.money.index',
            'steps' => [
                'After counting, open Record income & spending and stay on "Money in".',
                'Write what it is (e.g. "Sunday offering, first service"), choose "Sunday offering" and type the amount.',
                'Add a photo of the count sheet if you have one, then press "Record money in".',
                'Finance confirms it once it\'s banked. You can see when under "What you\'ve recorded".',
            ],
        ],
        'log-expense' => [
            'title' => 'Log money you spent for the church',
            'route' => 'admin.money.index',
            'steps' => [
                'Open Record income & spending and choose "Money out".',
                'Say what it was for, pick the category and type the amount and date.',
                'Add a photo of the receipt and press "Record money out". Someone else approves it.',
            ],
        ],
        'month-summary' => [
            'title' => 'See the month\'s money in and out',
            'area' => 'expenses',
            'route' => 'admin.income.index',
            'steps' => [
                'Open Income & summary. Confirm anything under "To confirm" first.',
                'Approve waiting expenses on Expenses & budgets.',
                'Back on Income & summary, read the totals and the breakdown by category. Pick another month at the top.',
                'Press Export for a spreadsheet of the month.',
            ],
        ],
        'send-announcement' => [
            'title' => 'Send an announcement to members\' phones',
            'area' => 'content',
            'route' => 'admin.content',
            'steps' => [
                'Open Content & announcements and write it under "New announcement". Watch the phone preview.',
                'Publish it.',
                'In "All announcements", press Send on it and choose email, WhatsApp and/or app notifications.',
            ],
        ],
        'new-event' => [
            'title' => 'Set up an event with tickets',
            'area' => 'events',
            'route' => 'admin.events.create',
            'steps' => [
                'Open Events & tickets and press New event.',
                'Fill in the details, then add ticket types. A price of 0 makes it free.',
                'Save and publish. On the day, check people in by scanning their ticket or typing its code in the box at the top of Events & tickets.',
            ],
        ],
        'library-desk' => [
            'title' => 'Lend and take back a library book',
            'area' => 'library',
            'route' => 'admin.library.desk',
            'steps' => [
                'When a member comes to collect, find them under "To hand over" and press "Hand over".',
                'When they bring it back, find it under "On loan" (or search) and press "Returned".',
                'If someone is waiting for it, they\'re told automatically that it\'s ready.',
            ],
        ],
        'add-staff' => [
            'title' => 'Add a staff member',
            'area' => 'settings',
            'route' => 'admin.settings.edit',
            'steps' => [
                'Open Settings & roles and go to Staff.',
                'Type their email and choose a role. The table shows what each role can open.',
                'They sign in at /admin/login. If they\'re new, they set their password from the email they get.',
            ],
        ],
        'publish-testimony' => [
            'title' => 'Publish a testimony',
            'area' => 'testimonies',
            'route' => 'admin.testimonies.index',
            'steps' => [
                'Open Testimonies. New ones are under the first tab.',
                'Read it and tidy any spelling.',
                'Publish it (the member is told and rewarded), or decline it with a kind note they\'ll see.',
            ],
        ],
        'reward-member' => [
            'title' => 'Give a member Crowns',
            'area' => 'tokens',
            'route' => 'admin.tokens.index',
            'steps' => [
                'Only the pastor in charge can do this. Open the treasury page.',
                'Under "Give or take", find the member, type the amount and a note, and choose Give.',
                'If the treasury is low, create more first under "Create or destroy".',
            ],
        ],
    ],
];

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

        'verses' => [
            'title' => 'Bible verses',
            'area' => 'content',
            'about' => 'The verses members read in Verses on their account (swipe up or tap for another, and save, share or copy one) and the verse that greets them once a day when they open the app. It starts with about 350 verses from the King James Version, grouped by theme.',
            'faqs' => [
                ['Why only the King James Version?', 'It\'s free to use. Most modern versions (NIV, NKJV, NLT, AMP) are copyrighted, and using hundreds of their verses in an app needs the publisher\'s permission.'],
                ['How do I take a verse out?', 'Press Hide. Members stop seeing it straight away, and it waits under Hidden if you want it back.'],
                ['Can members turn the daily verse off?', 'Yes, in My details on their account. It\'s on to start, and shows once a day, the first time they open the app.'],
            ],
        ],

        'permissions' => [
            'title' => 'Workers\' permission',
            'area' => 'permissions',
            'about' => 'Workers who won\'t be in church, or will be coming late, ask here from their account with a reason. Their HOD (in the unit area) and the pastors see the same requests and can grant or decline them; the worker is told straight away by email, WhatsApp or app notification.',
            'faqs' => [
                ['Who can ask?', 'Approved members who are workers or ministers, or who serve in a unit. They find "Permission" on their account page.'],
                ['Who sees a request?', 'The HOD and assistant of every unit the worker is in, and the pastors (anyone whose role covers Workers\' permission). That is the pastor in charge, administrators and pastors; the church office doesn\'t see them.'],
                ['What does "On the rota" mean?', 'The worker is on their unit\'s rota for that service, so someone needs to cover their duty.'],
                ['Does it affect follow-up?', 'Yes. A worker who asked to be away from a Sunday isn\'t counted as missing it, so no "we missed you" follow-up is raised for that Sunday.'],
                ['Can a decision be changed?', 'Yes, until the day of the service: grant a declined request, or withdraw permission that was granted. The worker is told each time.'],
            ],
        ],

        'members' => [
            'title' => 'Members',
            'area' => 'members',
            'about' => 'The church register. Approve people who joined online, keep profiles up to date, and see each member\'s attendance, milestones and follow-ups.',
            'faqs' => [
                ['Someone joined online. Why can\'t they use the app yet?', 'New sign-ups wait under "Waiting for approval" until someone approves them. Once approved they can use the community, the library, check in and earn rewards.'],
                ['Can I add members without them signing up?', 'Yes: "Add member" adds someone to the register, or import many at once from a spreadsheet (CSV). When you add someone you can send them a link to choose a password, or get a temporary password to pass on.'],
                ['How does a member I added log in?', 'With their email or phone number and a password. On their profile, under Login: "Send a link to set a password" emails them (and WhatsApps them, if it\'s on) a link that works for 7 days; send it again any time. "Give a temporary password" shows you a password once, with a button to send it on WhatsApp; they choose their own the first time they sign in. "Send N links" on the members list invites everyone with an email and no login at once.'],
                ['A member forgot their password.', 'They can press "Forgot your password?" on the login page if they have an email. If not, open their profile and give them a temporary password.'],
                ['Will members have to log in every time?', 'No: "Keep me signed in" is ticked to start, so they stay signed in on their phone for 90 days.'],
                ['What does "Inactive" mean?', 'Someone who has moved away or stopped coming. They stay on the register with their history but drop out of counts and follow-ups.'],
                ['How do I download the register?', 'Use Export: it gives a spreadsheet of the members you\'re looking at, with the filters you\'ve chosen.'],
                ['What are workers and ministers?', 'The order is member, worker, minister, then the pastors. Everyone serving in a unit is a worker: adding someone to a unit makes them one. Workers need the baptismal class, believers\' class and workers\' in training class. Ministers work closely with the pastors and also need School of Disciples (SOD) and Bible college; only a pastor can make a worker a minister, from the member\'s profile.'],
                ['How do I see who still needs training?', 'Use the Training filter: "Training not answered" for new workers and ministers who haven\'t said which classes they\'ve done, and "Classes still to do" for those with a required class outstanding.'],
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
                ['How do I share the work?', 'Assign each follow-up to someone on the team. They get an email, and it shows under their name. Each morning, anyone with overdue follow-ups gets one email listing them, and every Monday the pastors get a summary of the week.'],
                ['Do unit heads see follow-ups?', 'Yes: unit heads see follow-ups for people in their unit under Follow-up in their unit area, can call them and note how it went. "No answer" keeps it open for the team. Pastoral notes stay private to the care team.'],
                ['What happens when someone misses services?', 'After the number of Sunday services set in Settings & roles (2 to start), a follow-up is raised, their unit heads are emailed, and the member gets a "We missed you" message, once per absence. Change or switch off that message on Reminders & greetings.'],
                ['What is a second visit?', 'When a first-timer\'s card matches an earlier one (same phone or email), they\'ve come back. The follow-up becomes "Second visit": a call inviting them to join the family.'],
                ['Is there a script for calls?', 'Press "What to say" on any follow-up. Change the wording for each kind of call in Settings & roles › Follow-up & visitation.'],
            ],
        ],

        'visits' => [
            'title' => 'Visitation',
            'area' => 'care',
            'about' => 'Pastoral visits to members and visitors: at home, in hospital, after a bereavement or a new baby. Plan who goes and when, then write a short report.',
            'faqs' => [
                ['Who hears about a visit?', 'Everyone going is emailed when it\'s planned (or moved) and again the day before. Tick "Let them know we\'re coming" to tell the person too, by email and WhatsApp.'],
                ['Can a visit come from a follow-up?', 'Yes: press "Plan a visit" on a follow-up. When the visit is recorded as made, the follow-up closes with your report.'],
                ['Someone isn\'t on the register. Can I still visit them?', 'Yes: leave Member empty and type their name, with an address such as a hospital ward.'],
            ],
        ],

        'empowerment' => [
            'title' => 'Youth empowerment',
            'area' => 'empowerment',
            'about' => 'Programmes young people apply to on the website: each has a banner you can change any time, the choices on offer, and an application form you design. Review applications here and let people know.',
            'faqs' => [
                ['How do I start a programme?', 'Press "New programme", give it a name, then on "Page, choices & form" add a banner, write about it, set the dates and ages, add the choices people pick from, check the questions, and tick Published.'],
                ['Can I change the banner later?', 'Yes, any time: choose a new picture on "Page, choices & form" and save. The old one is removed. Without one, a designed banner shows.'],
                ['What can I ask on the form?', 'Name, phone, email, gender, date of birth, where they live and their choices are always asked. Add your own questions: short or long answers, numbers, dates, dropdowns, multiple choice, checkboxes and yes/no, each required or optional.'],
                ['How do I accept people?', 'Open an application, set it to Accepted, choose which option they\'re accepted for, and keep "Let them know" ticked to email (and WhatsApp) them. To do many at once, tick them in the list and use the bar at the bottom.'],
                ['What happens when a choice is full?', 'When you set places, the page shows how many are left. When none are left people can still pick it and go on the waiting list.'],
                ['How do I get the list into Excel?', 'Press "Download Excel" on the programme: every application with each answer in its own column, and a summary.'],
            ],
        ],

        'reports' => [
            'title' => 'Reports',
            'about' => 'Excel workbooks for each part of church life, for the dates you choose. Administrators and the pastor in charge only.',
            'faqs' => [
                ['What\'s in a report?', 'A Summary sheet with the key figures for the dates chosen, then sheets with every record behind them. Headers stay in view and every column can be filtered and sorted.'],
                ['Why can\'t I see the giving report?', 'Giving records are private to the pastor in charge, so only they can download it.'],
                ['Can I open them without Excel?', 'Yes: the files open in Google Sheets, Numbers and LibreOffice too.'],
            ],
        ],

        'register' => [
            'title' => 'Register someone',
            'about' => 'For any staff member: fill in a first-timer\'s welcome card, or put a new member on the register, at the door or after a service.',
            'faqs' => [
                ['Why is the new member waiting for approval?', 'Members added by staff who don\'t look after the register wait for the church office, like anyone who joins online. A unit you chose for them goes to the office as a request.'],
                ['The visitor has been before. What happens?', 'If their phone or email matches an earlier card, the care team gets a "Second visit" follow-up instead of a first call.'],
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
                ['Where does the Online number come from?', 'If the service is streamed on the church\'s connected YouTube channel, it fills in by itself while the stream is live: the most people YouTube showed watching at once. That includes people watching in the app, since the app plays the YouTube stream. Type a number to use your own instead; "Use YouTube\'s number" switches back.'],
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
                ['Who can make someone a worker?', 'Everyone in a unit is a worker, so the unit\'s head or assistant makes someone a worker by adding them to the unit, answering which workers\' classes they\'ve done. A worker can serve in several units. Someone who leaves their last unit becomes a member again (ministers stay ministers). Only a pastor makes a worker a minister.'],
                ['Can someone head two units?', 'No: someone can head one unit only. A worker or minister can serve in several units, and a minister can head a unit. The head and assistant are added to the unit they lead.'],
                ['How do members join a unit?', 'They ask from their account (or choose one when they sign up), answering which workers\' classes they\'ve done. Requests appear at the top of this page: Approve puts them in the unit, which makes them a worker; Decline tells them, with your reason. Unit heads can also add someone from their unit\'s Members page.'],
                ['How do I add a new unit?', 'Press "New unit" at the top of this page. Give it a name, a type, and optionally a head, an assistant and a line about what the unit does.'],
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
            'area' => 'blog',
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
                ['What are the Job vacancies, Business and Career groups?', 'Rooms for work life. In each, members get a helper beside the message box: "Post a job" (role, company, place, type, pay, how to apply, closing date), "Share my business" (with their market shop link when they have one) and "Share an opportunity" (courses, internships, scholarships), so posts are laid out the same way. Rename, reorder or lock them like any room.'],
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

    // The seller area (/vendor): keyed by the layout's `active`.
    'vendor' => [
        'home' => [
            'title' => 'Your shop at a glance',
            'about' => 'Start here: quick actions, your getting-started checklist, sharing, and new enquiries.',
            'faqs' => [
                ['How do buyers find my shop?', 'In the '.env('CHURCH_MARKET_NAME', 'Tribe Market').' on the church website while you\'re subscribed, and through links you share. Your shop has its own web address you can put on WhatsApp, Instagram and flyers.'],
                ['How do buyers pay me?', 'Directly, on terms you agree with them. Buyers call you, chat on WhatsApp or send an enquiry. Church members can also pay with Crowns for listings where you allow it.'],
                ['What does the checklist do?', 'It walks you through setting up: logo, banner, your story, your first listings with photos, subscribing and sharing. Each step ticks itself when it\'s done.'],
            ],
        ],
        'storefront' => [
            'title' => 'My online shop',
            'about' => 'How your shop looks: banner, logo, colour, tagline, announcement, your story, hours, delivery, returns, social links and sections.',
            'faqs' => [
                ['I don\'t have a logo or banner yet.', 'No problem: your shop shows a designed banner and a logo with your initials, both in your shop colour, until you add your own.'],
                ['What size should my banner be?', 'Wide, at least 1600 × 600. It\'s cropped to fit phones and computers, so keep the important part in the middle.'],
                ['What are sections?', 'Groups for your listings, such as "Cakes" and "Drinks". Buyers tap a section to see just those. Choose each listing\'s section when you add or edit it.'],
                ['Can I change my shop\'s name or phone number?', 'Yes, in Shop details. Your shop\'s web address stays the same, so links you\'ve shared keep working.'],
            ],
        ],
        'products' => [
            'title' => 'Products & services',
            'about' => 'Everything you sell: add, edit, hide, feature, put on sale or delete listings, each with up to 5 photos.',
            'faqs' => [
                ['How many photos can a listing have?', 'Up to 5. The first is the cover; choose a different cover any time. Large photos are made smaller for you, so phone photos are fine.'],
                ['How do I put something on sale?', 'Edit it and fill in "Was" with the old, higher price. Buyers see it crossed out with how much they save.'],
                ['What does Feature do?', 'Featured listings show first on your shop, under "Featured". Use it for best sellers and new arrivals.'],
                ['Hide or delete?', 'Hide takes a listing off your shop for now, and Show brings it back. Delete removes it and its photos for good.'],
            ],
        ],
        'share' => [
            'title' => 'Share',
            'about' => 'Ready-made pictures, captions, links and QR codes for your shop and every listing.',
            'faqs' => [
                ['Which picture should I use?', 'Square for WhatsApp, Instagram and Facebook posts, Status for WhatsApp status and stories, Wide for Facebook and X links, and the QR code for flyers and your stall.'],
                ['Can I see who came from my links?', 'You can see how many visits your shared links brought, here and on your Home page.'],
            ],
        ],
        'enquiries' => [
            'title' => 'Enquiries',
            'about' => 'Messages from buyers about your listings. Each is also emailed to you.',
            'faqs' => [['How do I reply?', 'Call or WhatsApp the buyer using their number, or reply to the email. Mark it read when it\'s handled.']],
        ],
        'token-orders' => [
            'title' => 'Crown orders',
            'about' => 'Members who got a listing with Crowns. Hand it over, then mark it done; the Crowns come to you.',
            'faqs' => [['What if I can\'t fulfil it?', 'Decline it and the member gets their Crowns back.']],
        ],
        'subscription' => [
            'title' => 'Subscription',
            'about' => 'Your shop shows in the market while your subscription is active. Pay for a period, use a coupon, or renew early.',
            'faqs' => [['Do I lose days if I renew early?', 'No: the new period starts when your current one ends.']],
        ],
        'shop' => [
            'title' => 'Shop details',
            'about' => 'Your shop\'s name, category, short description and how buyers reach you.',
            'faqs' => [['Where do enquiries go?', 'To your sign-in email, and they show under Enquiries.']],
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

        'vendor.home' => [
            ['#quick-actions', 'Quick actions', 'Add a product or service, design your shop, or share it, in one tap.'],
            ['#getting-started', 'Getting started', 'Your checklist. Work down it and each step ticks itself when it\'s done.'],
            ['#share-box', 'Share your shop', 'Send your shop to WhatsApp, Facebook, X or Telegram, or copy the link.'],
            ['.side__nav', 'Your menu', 'Everything for your shop: design, listings, sharing, enquiries and your subscription. On a phone, open it with the menu button.'],
        ],
        'vendor.storefront.edit' => [
            ['.storefront-preview', 'Preview', 'How the top of your shop looks. It changes as you choose a banner, logo, colour and tagline.'],
            ['.swatches', 'Shop colour', 'Buttons, sections and badges in your shop follow this colour.'],
            ['box:About your shop', 'About your shop', 'Your story, hours, where to find you, delivery and returns. Buyers trust shops that tell them this.'],
            ['box:Social media', 'Social media', 'Links to your Instagram, TikTok, Facebook and website show on your shop.'],
            ['#sections', 'Sections', 'Group your listings, e.g. Cakes and Drinks. Buyers tap a section to see just those.'],
            ['.page-head .actions', 'See it live', 'Open your shop as buyers see it.'],
        ],
        'vendor.products.index' => [
            ['.page-head .actions', 'Add a listing', 'Add a product or a service, with up to 5 photos.'],
            ['.box--flush', 'Your listings', 'Hide, show, edit or share each one. Featured and sale listings are marked.'],
        ],
        'vendor.products.create' => [
            ['.choice-group', 'Product or service', 'Services (photography, tutoring, catering) are booked for a date and have no stock.'],
            ['#photos', 'Photos', 'Choose up to 5 at once. The first is the cover; large photos are made smaller for you.'],
            ['input[name=compare_price]', 'On sale?', 'Put the old, higher price here to show a sale.'],
            ['select[name=shop_collection_id]', 'Section', 'Which section of your shop it shows in.'],
        ],
        'vendor.products.edit' => [
            ['#photos', 'Photos', 'Pick the cover, tick photos to remove, or add more (up to 5).'],
            ['input[name=is_featured]', 'Feature it', 'Featured listings show first in your shop.'],
            ['box:Delete this listing', 'Delete', 'Removes it for good. To take it down for a while, untick "Show it in the market" instead.'],
        ],
        'vendor.share.index' => [
            ['box:Tips', 'Tips', 'Which picture to use where: WhatsApp, Instagram, Facebook, flyers.'],
            ['#shop', 'Your shop', 'Pictures, a caption and a QR code for your whole shop.'],
        ],
        'vendor.subscription.index' => [
            ['box:Choose a plan', 'Plans', 'Pick how long. A coupon from the church office takes money off.'],
            ['box:Renew or extend', 'Renew', 'Renewing early adds the new time after your current period.'],
        ],

        'admin.care.index' => [
            ['.tabs', 'Open and done', 'Follow-ups still to do, and the ones already done with their outcomes.'],
            ['.box--flush', 'Who to call', 'Call or WhatsApp straight from the list, assign it to someone, and record how it went.'],
            ['#new-follow-up', 'Add one yourself', 'Most follow-ups are made automatically, but you can add one for anyone.'],
        ],

        'admin.visits.index' => [
            ['.tabs', 'Coming up and done', 'Visits still to make, the ones you\'re going on, and reports from visits made.'],
            ['#new-visit', 'Plan a visit', 'Choose the person, why, when and who\'s going. Everyone going is emailed and reminded the day before.'],
        ],

        'admin.empowerment.index' => [
            ['.box--flush', 'Programmes', 'Each programme, whether it\'s open, and how many have applied (and how many are new).'],
            ['#new-programme', 'Start one', 'Name it here; you\'ll add the banner, choices and form next.'],
        ],
        'admin.empowerment.show' => [
            ['#choices-summary', 'Choices', 'How many picked each option and how many you\'ve accepted. Tap one to see just those applicants.'],
            ['.tabs', 'By status', 'New, shortlisted, accepted, waiting list, not selected and completed.'],
            ['.bulk-bar', 'Many at once', 'Tick applications and change their status together, letting them know.'],
            ['.page-head .actions', 'View and export', 'See the page as applicants do, or download every application to Excel.'],
        ],
        'admin.empowerment.edit' => [
            ['#page', 'The page', 'Banner, words, dates, ages and how many choices each person picks. Tick Published to put it on the website.'],
            ['#choices', 'Choices', 'The empowerment options people pick from, with optional places.'],
            ['#form', 'The form', 'Add, reorder or remove questions, and choose which are required.'],
        ],
        'admin.empowerment.application' => [
            ['#decide', 'Decision', 'Set the status, choose what they\'re accepted for, keep a private note, and let them know.'],
        ],

        'admin.reports.index' => [
            ['box:Dates', 'Choose the dates', 'Pick a range, or one of the quick choices such as This month or Last year.'],
            ['.report-grid', 'Download', 'Each card downloads one Excel workbook for those dates.'],
        ],

        'admin.register.index' => [
            ['#visitor', 'A first-timer', 'Their welcome card. The care team is asked to call them within a couple of days.'],
            ['#member', 'A new member', 'Puts them on the register. The church office approves them if you don\'t look after the register.'],
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

        'admin.permissions.index' => [
            ['.tabs', 'Coming up and past', 'Requests for the coming services, waiting ones first, and the ones already gone.'],
            ['.prayer-card', 'A request', 'Who, which service, and why. Grant or decline it with a short note; they\'re told straight away.'],
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

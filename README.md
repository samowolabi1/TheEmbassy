# RCCG The Royal Tribe Embassy — website

The public website, built with [Laravel](https://laravel.com) 13 from the "RCCG The Royal Tribe Embassy — App & Website" design. Pages are Blade templates styled with plain CSS taken from the design's style guide; Vite bundles the CSS and the small amount of JavaScript.

## Pages

| Path | Page |
| --- | --- |
| `/` | Home: highlights carousel, declaration, announcements, messages, blog, events, Tribe Market, prayer request, give, app |
| `/about` | Story, mission, pastors, beliefs, units, plan your visit and FAQs |
| `/watch` | Live stream player, series and a filterable message library |
| `/blog` | Featured article, then articles by topic and search (`/blog?topic=Faith`, `/blog?q=money`), nine to a page; `/blog/{slug}` for each article and `/blog/feed` for RSS |
| `/gallery` | Photo and video albums by year; `/gallery/{album}` opens a full-screen viewer with swipe, download and a shareable link to each photo |
| `/events` | Featured event with countdown, monthly calendar, regular gatherings; `/events/{slug}` for RSVPs and tickets |
| `/give` | Tithes and offerings by bank transfer (the church's accounts in each currency), donations by transfer or online through Paystack, appeal progress |
| `/market` | The member marketplace for products and services; `/market/open-a-shop` to apply |
| `/notices` | Paid notice board for other churches and businesses; `/notices/advertise` to place one |
| `/join`, `/login`, `/account` | Membership sign-up, sign-in, and the member's own page: quick links to mark attendance, join online, messages, welfare, community, tokens and give; details |
| `/account/tokens` | The member's token wallet: balance, ways to earn, what they can get with tokens, their token orders and history |
| `/account/feedback` | Rate a recent service (what blessed you, what could be better), and send suggestions and see the church's answers |
| `/account/prayer` | Send a prayer request, follow it up, read the prayer team's replies, and mark it answered (then share it as a testimony) |
| `/account/prayer/wall` | The members' prayer wall: requests members chose to share with the church, with "I prayed" |
| `/account/appointments` | Book a visit with a pastor at an open time, and see your visits (confirmed, moved or declined) |
| `/library` | Borrow books from the church library, renew them, join a waiting list and review them |
| `/account/testimonies` | Share a testimony, choose how your name is shown and where it's shared, and see what became of it |
| `/testimonies` | Members' published testimonies, by kind (healing, provision, breakthrough…), with "Praise God" |
| `/account/messages` | The member's messages: what the church sent out, announcements, and their unit's messages |
| `/services/{id}/join` | Join a service online: the stream plays (or Zoom/Meet opens) and the member is marked present |
| `/welcome` | First-timer card for visitors; print it as a QR code at the door |
| `/check-in` | Members check in to a service, at church (location and screen code) or online (stream code) |
| `/contact`, `/privacy`, `/terms` | Contact form and legal pages |
| `/community` | The members' community (approved members and staff only): chat rooms, polls, the Bible quiz and birthdays |
| `/sitemap.xml` | Every public page, article, album and upcoming event, for search engines |
| `/newsletter/confirm/…`, `/newsletter/unsubscribe/…` | Links in the weekly email to confirm a sign-up or stop it |

**The app.** The website installs as an app (a progressive web app): "Install the app" on the home page, or Share › Add to Home Screen on iPhone. It opens full screen from the home screen, shows an offline page without a connection, and can receive notifications once someone turns them on (home page or their account). There's nothing to publish in the App Store or Google Play; if the church later publishes store apps, put their links in Settings & roles and the store buttons appear.
| `/vendor` | Sellers manage listings, enquiries, subscription and shop details (sign in at `/vendor/login`) |

## Accounts and roles

There are three kinds of account, all in the `users` table:

- **Members** join at `/join` and wait for the church office to approve them. They update their details and use the member features at `/account`. Members never pay to use the site.
- **Sellers** apply at `/market/open-a-shop`; approving the shop emails them a link to set a password.
- **Staff** have a role from `config/roles.php`, and each role opens only its own admin areas:

| Role | Admin areas |
| --- | --- |
| Pastor in charge (super admin) | Everything, and the only role that sees giving and controls the token currency (creating tokens, giving them out, what activities earn, the token's value) |
| Administrator | Everything else, including Settings & roles |
| Pastor | Everything except Settings & roles, including welfare cases and approvals above the HOD's limit |
| Finance | Giving (funds and bank accounts), expenses & budgets, the market |
| Church office | Members, attendance & first-timers, follow-up & care, units, the weekly email, the market, website content (announcements, home slider, About page, Bible verses), the blog, gallery, community moderation, notices, events, prayer, messages |
| Media | Website content, the blog, gallery, community moderation, notices, sermons & live, events, and the media unit's tools (live studio, requests, file library, birthday cards) |
| Ushering | Attendance & first-timers, events (door check-in) |
| Website manager | The website: announcements, the home page slider, the About page, Bible verses, the blog and the gallery |
| Blog writer | The blog only: write articles and send them for approval (an editor publishes them) |
| Librarian | The library desk and book catalogue |

Create the first administrator from the command line; they add everyone else in **Settings & roles**, which emails each new person a link to set their password:

```sh
php artisan admin:create pastor@example.com --name="Pastor Ade" --super   # the pastor in charge (super admin)
php artisan admin:create office@example.com --name="Church office"      # an administrator; asks for a password, or generates one
php artisan admin:revoke pastor@example.com                       # removes staff access
```

Sign-in is limited to five wrong attempts a minute per email. The site uses Lagos time (`APP_TIMEZONE`).

**Forms:** every password box has an eye button inside it to show or hide what's typed. Money boxes show the ₦ sign inside and add commas as you type (₦1,234,567.89); Crown amounts show 👑 (or whatever icon the currency has). The server still receives a plain number, and without JavaScript they're ordinary number boxes. To format a new money box, add `data-money` to its `<input type="number">` (or `data-money="👑"` for another sign); a `step` with decimals allows kobo.

## Admin dashboard

**The sidebar** can be hidden on a computer with the arrow at its top (a "Menu" button brings it back; the choice is remembered), and each group (People, Money, Content…) folds with a tap on its name. The group with the page you're on always stays open. On phones the menu folds away behind the menu button at the top. The same works in the unit leaders' area and the sellers' area.

**Help on every page.** A **Help** button sits in the corner of every admin page. It opens a panel that says what the page is for, answers the questions people usually ask about it, links to related guides, and offers **Take the tour**: a short walkthrough that highlights each part of the page in turn (Next, Back, Skip; arrow keys and Escape work too). The first time someone opens a page with a tour, a small "New to this page? Take a quick tour" note offers it once.
- **Help centre** (admin › Help centre, for all staff): step-by-step guides for common jobs (approve a member, run Sunday check-in, follow up a first-timer, record the offering, log spending, the month's summary, send an announcement, set up an event, lend a library book, add staff, publish a testimony, give Crowns) with **Show me around** buttons that open the page and start its tour, plus every page's questions, with a search box. People only see guides and questions for the areas their role covers.
- **Tooltips**: a small ⓘ beside fields and sections that need a word of explanation. Hover, focus or tap it.
- **Changing the help**: all of it lives in `config/help.php`: each page's description and questions, the tours (each step points at something on the page, either a CSS selector or `box:Heading` for the box with that heading) and the guides. Add a tooltip anywhere with `<x-tip text="…" />`.

Staff sign in at `/admin`. Every area in the design's sidebar is built:

- **Dashboard**: members, first-timers, giving (administrator only), tokens earned and market income this month, the last eight Sundays' attendance, what needs attention, giving by fund, latest gifts, upcoming events, prayer requests and new members. Each person only sees the panels their role covers.
- **Members**: the register by status (members, waiting for approval, inactive), search and filters, profiles with milestones (baptised, believers' class, workers' training), their token wallet and recent tokens, approval, and CSV import and export.
- **First-timers & follow-up**: cards from `/welcome` or typed in by staff; mark each person as called with a note, and add them to the register.
- **Attendance**: schedule services with self check-in at church and online, show the code screen, see who checked in, tick people present by hand, and record headcounts.
- **Follow-up & care**: follow-ups for first-timers, new members and members not seen lately, assigned to the care team with outcomes recorded.
- **Units & fellowships**: the service units and house fellowships members choose from.
- **Weekly email**: goes out every Monday morning (time set in Settings & roles) with the week's declaration, recent announcements, upcoming events, last week's messages and new articles. Preview it, send it early with "Send now", and see past sends. Sign-ups confirm by email before they get anything; every email has a one-click unsubscribe link. Emails go out about 120 a minute so mail services don't block them, and a restart carries on where it stopped without emailing anyone twice.
- **Giving**: the church's bank accounts in each currency (naira, dollar, pound, euro and more, with sort code, routing number, SWIFT and IBAN where they apply), marked for tithes and offerings, donations, or both; funds, each set as bank transfer only (tithes and offerings) or also payable through Paystack (donations); cash and transfers received recorded by finance staff.
- **Giving is private to the pastor in charge.** Only the pastor in charge (super admin) sees giving records and totals: transactions, fund totals and balances, the giving report export, the dashboard's giving panels and a member's giving on their profile. Finance staff and pastors record what comes in but don't see it afterwards, and members don't see a giving history on their account. Givers still get an email receipt for an online donation, and an appeal's public progress bar on the Give page shows the total raised.
- **Record income & spending** (every staff member): write down money that came in (offerings counted after a service, thanksgiving, pledges, donations, event income, sales, hall and equipment hire, welfare contributions and more) or went out, with the date, how it came in (cash, transfer, POS), notes and a receipt or count sheet. Staff see what they've recorded and whether it's been confirmed, approved or declined (with the reason).
- **Income & summary** (finance and pastors, the "Income, expenses & budgets" area): confirm or decline recorded income (nobody confirms their own, except an administrator). Each month shows money in, money out and what's left over, both broken down by category, and exports to one CSV. The income and expense categories are kept here: add new ones, rename them (past entries and budgets follow) or hide ones no longer used. Tithes and online giving stay under Giving, which only the pastor in charge sees.
- **Expenses & budgets**: spending logged with a receipt, approved by someone other than the person who logged it, against monthly budgets per category; CSV export.
- **Library**: the lending desk (hand books over, take them back, chase overdue ones, the waiting list) and the book catalogue. See [The church library](#the-church-library-and-book-reviews).
- **Tribe Market**: shop applications, shops and subscription status, plan prices, coupons, and subscription payments with CSV export.
- **Content & announcements**, **Notice board**, **Events & tickets**, **Prayer requests**, **Contact messages**.
- **Sending announcements**: "Send" on a live announcement emails it to approved members (ticked to start), sends it on WhatsApp to members who haven't turned WhatsApp off in their account, notifies every phone that turned on app notifications, or opens WhatsApp with the message ready to post in the church groups or channel. The page shows how many people each way reaches, and progress. Sending the same announcement twice in a day needs an extra tick. Text messages are no longer sent, to save fees.
- **Blog**: write articles in Markdown with a formatting toolbar (headings, bold, links, lists, scripture cards, the "reflect" box) and pictures uploaded straight into the text; a cover photo with a description for screen readers; draft, publish now or schedule for later; preview before publishing; feature one article at the top of the blog. Changing an article's address keeps the old one working (it redirects). Raw HTML and unsafe links are removed when an article is saved.
- **Approval for blog writers**: someone with the Blog writer role can't publish. They choose **Send for approval** (with an optional time to go live), and the website managers and media team (or the administrators, if there are none) are emailed. The article waits under **Waiting for approval**, with a count on the Blog menu, and anyone else with the blog can **Approve and publish** it (now, or at the writer's chosen time) or **Send back with a note**. Publishing it from the editor approves it too. The writer is emailed either way. Writers see and open only their own articles, and can't change one once it's approved.
- **Sharing articles**: each article has share buttons (WhatsApp, Facebook, X, Telegram, LinkedIn, the phone's share sheet and copy link) that send the title, the author's name and the link. When the link is pasted anywhere, the preview shows a picture with the title, the author and the church's name, and the description starts "By [author]". At the end of each article, "Share this article" offers that picture as a square post or a WhatsApp status/story, with a ready-made caption.
- **The home page header** is a full-width slider: each slide fills the header with its photo (which drifts slowly) or a brand colour with the crown. The headline rises in line by line, the words and buttons fade up, and a frosted card floats beside them (next service, a date, the sermon series or a verse). Story-style bars along the bottom show each slide's progress and name; slides move on every 7 seconds, hold while the mouse rests on them or the keyboard is on them, swipe on phones, and can be paused. A gold strip of words (Worship, The Word, Family, Purpose…) scrolls underneath. For people who turn on "reduce motion", nothing moves by itself.
- **Pages**: the About page (headline, story and photo, mission, vision, values, leaders with photos, beliefs, first-timer questions), and on the home page, the testimony and the **slider** at the top. Each slide has:
  - **Words:** a small label (outline or gold), a title (with line breaks), a highlighted gold line and a short text.
  - **Buttons:** up to two, each going to a page on the site (`/events`), a full address, or `@youtube`, `@sermon` (the newest sermon) or `@share`.
  - **Picture:** a photo (wide, about 3:2) with a description, or a colour.
  - **Something over the picture:** next Sunday's time and place, a date badge, the current sermon series, or a scripture panel.

  Slides can be reordered, hidden, or set to "show until" a date, so an event slide disappears after the event. Clearing a title removes the slide and its photo, the empty row adds a new one (up to 8), and the dots and count follow the number of slides. Until someone edits them, the four starting slides from `config/content/home.php` show. Units on the About page come from the units' "What they do" lines.
- **Gallery**: albums with a date, description and optional event; drop in up to 40 photos at a time with an upload progress bar, add YouTube videos, write captions, set the order and the cover, then publish. Photos are turned upright, resized (2000 px, plus a 640 px thumbnail), saved as WebP, and stripped of location and camera data.
- **Sermons & live**: the message library and series on the home and Watch pages, and the YouTube stream in the Watch page player.
- **Settings & roles**: staff and their roles, and the church's details (address, service times, bank details, live stream, social links). Saved values replace the placeholders across the site.

## Members' app: attendance, joining online, welfare, messages

Members do all of this from their account (or the installed app):

- **Mark attendance**: at church by phone location and the screen code, or online. When a service is on, the account page shows "… is on · Join now".
- **Join online services**: staff can add a Zoom, Google Meet or YouTube link to each service (Attendance › the service). Joining from the account plays the YouTube stream inside the app, or opens Zoom/Meet. Keeping the stream playing for a few minutes (5 by default, set in Settings › Attendance check-in) or opening the meeting through the app marks the member present online. Without a link it uses the stream from Sermons & live; the code on the stream still works for people watching elsewhere.
- **Welfare packages**: the welfare unit sets up packages (what's in them, how many, when applications open and close, where to collect) under Welfare packages. Members apply from Welfare in their account with their household size; the HOD approves, waitlists or declines (never past the number of places), and the member is emailed and notified. Approved members get a reference (PK-XXXXXX) to show at collection, where the unit marks it collected. Applications are private to the welfare team and pastors; CSV export for the list. One-off requests for help work as before.
- **Messages**: announcements, broadcasts sent from admin and their unit's messages in one inbox with an unread count. Notifications for broadcasts and unit messages open the inbox.

## Tokens: the app's own currency

**The name:** members see the currency as **Crowns** (👑) to start. The pastor in charge renames it and picks its icon on the treasury page (for example Stars ⭐ or Jewels 💎), and every page, email and message follows. This guide and the code still call them tokens.

Tokens are a closed currency inside the app. **1 token = ₦2** (the pastor in charge can change this). Nobody buys tokens with money and nobody cashes them out; they're earned by taking part and spent inside the app.

**Members only.** Only approved church members (an active member record) can earn, hold, send, receive or spend tokens. Visitors, staff accounts without a member record, and sellers who aren't members never see a token option: non-member sellers list items for money only and pay their shop subscription with money (Paystack) exactly as before. If someone stops being a member, their listings stop taking tokens and they can't spend or receive any more; refunds already owed to them still go back to their wallet, and the pastor in charge can still take tokens back or put the wallet on hold.

**Who controls them.** Only the **pastor in charge (super admin)** — the `super` role, created with `php artisan admin:create pastor@church.org --super` — can create (mint) or destroy tokens, give or take tokens from a member, put a wallet on hold, set how many tokens each activity earns, set a token's naira value, and switch member-to-member sending on or off. All of it is on **admin › Token treasury**. Pastors and the church office can see the treasury and members' tokens but can't change anything; nobody else can make a pastor in charge.

**How the money supply works.**
- **Treasury.** New tokens only come into being when the pastor in charge mints them, and they go into the church treasury.
- **Rewards.** Rewards are paid *out of* the treasury, never created from nothing. If the treasury runs low, rewards pause and the treasury page says so until more are minted.
- **Recycling.** Tokens spent on **shop subscriptions** and **event tickets** go **back into the treasury**, to be earned again.
- **Market sales.** Tokens spent in the **market** go to the seller, who can spend them on their subscription, which also returns them to the treasury.
- **Destroying tokens.** The pastor in charge can destroy unused treasury tokens to shrink the supply.
- **Books.** Every movement is a transfer from one account (the treasury, a member, or escrow for market orders) to another. All balances together always equal tokens created less tokens destroyed. The treasury page shows "books balance", and a daily job checks it and logs an error if not.

**Earning** (each once per occasion; tapping twice or refreshing never adds more):

| Activity | Tokens to start |
| --- | --- |
| Marking attendance at church (or an usher marking them present) | 10 per service |
| Joining a service online (from their account, or with the stream code) | 5 per service |
| Serving on the rota at a service they attend | 5 |
| Four Sundays in a row | 20 bonus, every four |
| Checking in to a unit meeting | 5 |
| Playing the Bible quiz | 5, plus 1 per right answer |
| First message in the community each day | 1 |
| Saying the weekly declaration ("I declare it" on their tokens page) | 2 a week |
| Completing their profile (birthday, address, unit, fellowship) | 10 once |
| Membership confirmed | 20 once |
| Birthday | 10 a year |

**Spending and moving.**
- **Market:** sellers who are members tick "Members can get this with tokens" on a listing and set a token price.
  - The buyer's tokens go into escrow and they get a code (TK-XXXXXX). The seller marks the code handed over and the tokens become theirs.
  - If the seller declines, the buyer cancels, or the order is 14 days old, the tokens return to the buyer.
- **Shop subscriptions:** sellers who are members can pay at the token rate. For example, a ₦5,000 month is 2,500 tokens. The tokens go back to the treasury.
- **Event tickets:** members can pay for a booking with tokens at the token rate. The tokens go back to the treasury, and event takings show money and tokens separately.
- **Sending:** members can send tokens to another member, found by phone number or email, with a note. There's a daily limit, 1,000 to start.
- **Wallet on hold:** the pastor in charge can put a wallet on hold. It can still receive tokens but can't spend or send them until it's released.

**What isn't possible, and why:**
- **Buying tokens with money, or cashing them out to naira.** That would make tokens stored value or e-money, which in Nigeria needs a CBN licence. It would also go against "members never pay to use the platform". Tokens stay a closed loop.
- **Tithes, offerings or donations in tokens.** Giving has to be real money; tokens aren't income to the church.
- **Stopping people trading tokens privately for cash outside the app.** The app can't see that. The daily sending limit, wallets on hold and the full history of every movement make it easy to spot and stop.
- **Paying sellers money for tokens they receive.** The app doesn't do this. If the church decides to buy tokens back from sellers, it does so outside the app and then takes the tokens back into the treasury, which keeps the books straight.

## The members' community

Approved members (and staff) get **Community** in the menu and on their account page.

- **Rooms**: General, Prayer & praise, Word & devotion, Campus life, Career, Job vacancies, Business and Banter & fun to start (an existing "Career & hustle" room becomes Career, keeping its messages).
- **Work groups**: Job vacancies, Business and Career each have a helper above the message box that lays posts out the same way: **Post a job** (job title, company, where, type, pay, what they're looking for, how to apply, closing date), **Share my business** (name, what you sell, where and delivery, contact, and a link that starts as the member's market shop when they have one), and **Share an opportunity** (courses, internships, scholarships: who it's for, details, how to apply, deadline). The helper writes into the message so the member can check it before sending; links in posts are clickable. Staff add, rename, reorder and delete rooms. A room can be tied to a unit so only that unit's members see it, or set to "announcements only".
- **Chat**: text with clickable links, photos (resized, location data removed), replies, six reactions (🙏 ❤️ 😂 🔥 👏 🙌), and polls that close after an hour to a week. People edit their own messages for 15 minutes and delete them any time. Messages appear for everyone within a few seconds without reloading, unread counts show on each room, and people who turned on app notifications get one when someone replies to them. Everything also works without JavaScript.
- **Fun**: a weekly **Bible quiz** staff write in admin (timed questions, one go each, scored on the server, leaderboard with the fastest time breaking ties; answers are shown once it closes), and **birthdays this week** (day and month only, never the year).
- **Safety**: house rules on the community page; members report a message with a reason; moderators (the "Community" area: administrators, pastors, church office and media) keep or remove it, and can mute the writer for 1 to 30 days with a reason they see. Moderators can remove any message and post in announcements-only rooms. Removed messages show as "Message removed" so replies still make sense.

## Attendance and follow-up

**Members check themselves in** at `/check-in` (or by scanning the QR code on the church screen), from 30 minutes before a service until 30 minutes after it ends. Staff schedule services under **Attendance**, optionally repeating weekly, and choose whether each takes in-person check-in, online check-in, or both.

- **At church**, the member's phone location must be within the radius the church sets around the venue (150 m by default) and accurate enough (200 m). A phone's location can be faked with a browser extension, so members also enter the six-digit code on the **check-in screen**, which changes every minute. Churches that would rather not use the code can switch it off in Settings. Only the distance from the venue is stored, never the member's coordinates.
- **Online**, members enter the code shown on the live stream while check-in is open; codes from the last three minutes are accepted to allow for stream delay. Show the code screen as a browser source or overlay in the streaming software. This counts people who were really watching, rather than counting views.
- Each member checks in once per service. Ushers can still tick people present by hand (no phone, no account), and record each service's headcount.
- **Online headcount from YouTube.** While a streamed service is live on the connected YouTube channel, the app asks YouTube every minute how many are watching and keeps the highest number. That fills the service's **Online** headcount by itself. People watching in the app are counted in it too, because the app plays YouTube's own player, so nobody is counted twice. If staff type a different Online number, theirs is kept (YouTube's peak still shows beside it), and **Use YouTube's number** switches back. It needs the YouTube connection, works for broadcasts the app created, and only appears when the channel shows its viewer count. Named online check-ins are a separate list of which members attended, and are never added to the headcount.
- Location needs HTTPS: browsers only share location with secure sites.

Set the venue under **Settings & roles › Attendance check-in**. The easiest way is to stand inside the church and press "Use my location".

**Follow-up & care** is a single list for the pastoral team. Follow-ups are raised automatically, and can also be added by hand:

| Who | When | Due |
| --- | --- | --- |
| First-timers (cards from `/welcome` or typed in) | when the card is added | within 2 days |
| Members who joined online, once approved | on approval | within a week |
| Visitors who come back (a card matching an earlier one by phone or email) | when the card is added | within 2 days: "Second visit", inviting them to join |
| Active members who've missed Sunday services | after 2 in a row (adjustable) | the same day |

Only Sundays where attendance was actually taken count as missed, members aren't counted as missing Sundays before they joined, and anyone seen at any service since (midweek included) isn't flagged. When someone comes back, their follow-up closes itself. Staff assign follow-ups to each other (the person gets an email), call or WhatsApp from the list, and record how it went. Each follow-up has **What to say**, a short call script for its kind, edited in Settings & roles › Follow-up & visitation. Member profiles show attendance over the last eight Sundays, their follow-up history and their visits.

**When someone misses services** (2 Sunday services in a row to start; Settings & roles › Follow-up & visitation):
- a follow-up is raised for the care team;
- the heads of the member's units are emailed, and see it under **Follow-up** in their unit area, where they can call, see the call script and note how it went ("No answer" keeps it open for the team; pastoral notes stay private to the care team);
- the member gets a **"We missed you"** message, once per absence, by email (and WhatsApp or app notification if chosen). Its wording, how many missed services, and the ways it's sent are on Reminders & greetings, where it can be switched off.

**Keeping it moving:** each morning, staff with overdue follow-ups get one email listing them; every Monday at 7:30 the pastors get a summary (follow-ups raised, done and reached, overdue and unassigned, visits made and planned, first-timers and "we missed you" messages). Both can be switched off in Settings & roles.

**Visitation** (admin › Visitation, for the care team): plan a visit to a member, or to anyone by name (a visitor, someone in hospital), with why (pastoral, not seen lately, welcome, hospital, bereavement, celebration, welfare, prayer), when, the address, notes and who's going. Everyone going is emailed when it's planned or moved and reminded the day before; "Let them know we're coming" tells the person too. Afterwards, record how it went with a short report. "Plan a visit" on a follow-up links the two, and recording the visit as made closes the follow-up. Tabs show visits coming up, your own, done and cancelled; the sidebar counts your visits in the next week.

**Registering people:** any staff member (ushers, media, finance and so on) can use **Register someone** in the sidebar to fill in a first-timer's welcome card or add a new member. Staff who look after the register add approved members (with their unit); anyone else's wait for the church office to approve them, and a chosen unit becomes a request.

`php artisan care:scan` runs every morning at 7 and `php artisan care:weekly` on Monday mornings, from the scheduler.

## Units and their heads of department

Each unit (choir, ushering, media, welfare…) has a **head of department (HOD)** and an assistant, set in admin › Units & fellowships. They sign in with their member account and press "Lead {unit}" on their account page to open the unit's own area at `/units/{id}`:

- **Overview**: members, Sunday attendance rate, the next meeting, the rota, members not seen for three weeks, and birthdays coming up.
- **Members**: add people to the unit (or move them in from another), remove them, call them.
- **Attendance**: each member against the last eight Sundays where attendance was taken and the unit's recent meetings, with an attendance rate.
- **Meetings**: rehearsals, planning or prayer meetings at a place or online (Zoom, Meet, Teams link). Members are emailed, see the meeting on their account page and check in with a six-digit code the HOD shows (it changes every minute), or the HOD ticks them. Minutes are kept with the meeting.
- **Rota**: who serves at each coming service, in what role. Members see their duties on their account page and get an email two days before (`units:remind`).
- **Messages**: emailed to the whole unit and shown on members' account pages.

Staff who manage units (church office, pastors, administrators) can open any unit's area. A unit's type adds tools: **Welfare** or **Media**.

### Workers and ministers

The order is **member**, then **worker**, then **minister**, then the pastors. Someone must be an approved member before anything else.
- **Workers:** everyone serving in a unit is a worker, and a worker can serve in **several units**. Only a unit's head or assistant, the pastors and the church office put people in units. Joining a unit makes a member a worker; leaving their last unit makes them a member again. Their first unit is their main unit, shown where one unit is named; each of their units' rosters, messages, meetings, rotas and community rooms include them. Workers need the **baptismal class**, **believers' class** and **workers' in training class**.
- **Ministers** work closely with the pastors. Only a pastor (or the pastor in charge) makes a worker a minister, or takes it away, from the member's profile in admin. Ministers need the workers' classes plus **School of Disciples (SOD)** and **Bible college**. A minister can serve in units and head one too, and stays a minister if they leave their units.
- **Asking to join:** members ask to join a unit from their account (My units › Ask to join a unit), with an optional note and their answers about the workers' classes; a unit chosen when signing up is a request too. Requests go to the pastors and church office on Units & fellowships (the sidebar shows how many are waiting), who approve (the member joins the unit and becomes a worker) or decline with a reason the member sees. Members are emailed (and WhatsApp'd) either way. The unit's head sees who's asking on the unit's Members page, and adding them there answers the request.
- **New units:** "New unit" on Units & fellowships adds a unit with its type, HOD, assistant and what it does (members see this when they ask to join).
- **Unit heads:** someone can be head (HOD) of **one unit only**; Units & fellowships shows who already heads a unit and won't let them head a second. The HOD and assistant are added to the unit they lead, and stay in it until they're changed there.
- **Training questions:** whoever adds someone to a unit, or makes them a minister, answers each required class: done or not yet. Someone who became a worker another way (approved with a unit, or already in a unit when this was added) is asked on their account page until they answer. The unit's Members page shows each person's training ("All done", "Still to do: …" or "Not answered yet") with an Update button; members can update their own answers; the office edits them on the profile.
- **Telling them:** the member is emailed (and WhatsApp'd, if it's on) when they become a worker or minister, with any classes still to do.
- **Finding them:** the members list filters by role and by training ("Training not answered", "Classes still to do"), and the export includes role and every class.
- **Message centre:** messages to members can go to workers, ministers, both, or members only, and Contacts has a "Workers & ministers" tab.

### Welfare

The welfare unit's HOD and assistant (and pastors) run a confidential case desk:

- **Requests** come from members (account › Welfare) or are opened by the team for a member or someone outside the church. Categories: financial hardship, medical, bereavement, food, rent, school fees, job loss, celebrations, other.
- **Privacy**: a case is only visible to the welfare HOD and assistant, pastors and administrators, and the case worker it's assigned to. Emails only carry the case reference, and the export has no names or details. Members see where their own request is up to, never the internal notes.
- **Approval**: the HOD approves support up to a limit set in Settings & roles (₦50,000 to start); above it, the case goes to a pastor, who is emailed. The member is told the decision.
- **Payments** are recorded against the case (transfer, cash, paid directly to a hospital or school, items bought), can't exceed what was approved, and each one is added to the church's approved expenses as "Welfare support, case WF-…". The month's giving is shown against an optional welfare budget.
- **Case log**: calls, visits, support and notes, with a next check-in date; the "Due a check-in" tab lists cases to follow up. Case workers (welfare unit members) log their visits from their account page.
- **Celebrations**: birthdays and wedding anniversaries in the next 30 days, with a WhatsApp greeting to send.

### Media

The media unit's team (its members, the HOD and assistant, and staff with the Media role) gets:

- **Equipment**: the inventory with asset tags, serial numbers, where it's kept, condition and value. Leaders check items out to members with a due date and check them back in; a faulty return or a fault reported by anyone on the team opens a repair, and leaders log the fix and its cost.
- **Service setup**: each service starts from the unit's checklist (the HOD edits it), with tasks assigned to members and ticked off on the day, next to the stream title and description and the unit's rota.
- **Streaming**: the stream servers and keys the streaming software (OBS, vMix or a hardware encoder) needs, stored encrypted and shown in full only to leaders. "Go live on the website" puts the YouTube stream in the Watch page player with the live badge; ending it leaves the replay. Browsers can't send a video stream themselves, so the stream comes from the encoder; the app keeps it organised.
- **YouTube broadcasts**: connect the church's channel once with "Sign in with Google" (on the Service setup page). The app then creates a YouTube broadcast for every service with online check-in, 7 days ahead (adjustable in Settings), titled and scheduled for the service and bound to one reusable stream, so the server and key in OBS never change. Broadcasts start when the encoder starts and stop when it stops; within a minute the Watch page goes live and afterwards shows the replay (`youtube:sync`, every minute). Leaders can also create a broadcast by hand, send an edited title and description to YouTube, or remove one. A broadcast that fails to create (e.g. live streaming not yet enabled on the channel) shows the reason and waits for "Try again", so it doesn't use up YouTube's daily limit.
- **Social media**: team members draft posts (caption, photo or video, platforms, optional time) and send them to the HOD, who approves, schedules or publishes. **Facebook Page and Instagram** are posted by the app through Meta's Graph API; Instagram videos are published once Instagram has processed them (`social:publish` runs every minute). **YouTube, X, TikTok and WhatsApp** get a share kit: copy the caption, download the file, open the platform. To connect Facebook and Instagram, an administrator adds the Page ID, a Page access token (with `pages_manage_posts` and `instagram_content_publish`) and the Instagram business account ID in Settings & roles; the token is stored encrypted. Meta fetches photos and videos from the site, so posting only works once the site is on a public HTTPS address.

- **Live studio** (StreamYard-style graphics, with OBS doing the streaming): the team puts things over the stream from the Live studio page during the service. That covers full-screen scenes ("Starting soon" with a countdown to the service, "Be right back", "Thanks for watching", or your own words), a name caption for whoever is speaking, a scripture, a scrolling ticker, and the logo in the corner. Captions and scriptures can be saved to show again with one tap, and "Clear everything" empties the screen. OBS shows them through the overlay link, added once as a 1920 × 1080 browser source above the cameras; the page shows a live preview of what OBS sees. The link has a long secret in it, and the HOD can replace it. **Remote guests** (a visiting minister, a testimony from abroad) get a link that opens their camera in the browser on any phone or laptop, and OBS adds them with a second link. This uses VDO.Ninja, a free service that connects the guest's camera straight to OBS. **How it differs from StreamYard:** StreamYard mixes the cameras and sends the stream from its own servers, so it runs in a browser with no OBS. This app gives the same graphics, guests and branding, but the stream still comes from OBS on the media computer, as it does now. Doing the mixing in the browser would need a paid video server (for example LiveKit or Cloudflare Stream).
- **Birthday cards**: six built-in designs: Royal crown (purple and gold, with a crown and sparkles), Neon party, Balloons, Black and gold, Pastel pop, and Sunset vibes. The HOD (or an administrator) ticks the ones to use; with more than one, each member gets one of them, a different one most years. They also set the headline, message and verse on the built-in designs. **Upload your own design**: a finished picture made in Canva or Photoshop (portrait 1080 × 1350 works best), with space left for the photo and name. On the next page they place the photo (circle, rounded square or none; position, size and border colour) and the name (position, size, font, colour, capitals, shadow), with the day and message optional. A live preview follows the sliders. Cards are drawn at 1080 × 1350, the size for Instagram, Facebook and WhatsApp status. Each has the member's photo (or initials) and name, and the day, never the year. The page lists birthdays in the next 30 days: download any member's card in any design, add their photo, or "Make a social post" to draft a post with the card for approval. Fonts are bundled in `resources/fonts` (SIL Open Font License).
- **Requests**: unit heads (and their assistants) and church staff ask the media unit for something from "Ask the media team" on their account page (staff also have it in the admin menu). Types: flyer or poster, social media graphic, video, photo coverage, livestream or recording, slides, or something else. They give the details, the event date and when it's needed (7 days' notice is suggested), and can attach up to 5 files (logos, photos to use, examples). The media HOD and assistant are emailed. On the team's Requests page (To do, Mine, Delivered, Closed, with overdue and due-soon flags), any team member can take an unassigned request; leaders assign, reassign or decline with a reason. Whoever has it delivers files and links with a note. The person who asked is emailed at each step. They can download the work, mark it done or send it back with changes, and cancel while it's open. Both sides can message on the request; team notes stay inside the team.
- **File library**: a private store for photos, videos, audio, designs (PSD, AI, SVG and others), documents and zips, plus links to big files kept on Google Drive, Dropbox or YouTube. Leaders make folders; anyone on the team uploads (up to 20 files at a time, 500 MB each by default, set with `MEDIA_MAX_UPLOAD_MB`), tags and searches. Pictures get small previews. Work delivered for requests appears under "From requests" and can be filed into a folder. Files sit on the private disk (`storage/app/private`), and the app only serves them to the media team, or to whoever asked for a request. Design files, SVGs and other non-media files are only ever downloaded, never opened in the browser. For big uploads the server's own limits must be raised to match: PHP `upload_max_filesize` and `post_max_size`, and the web server's body size (for example nginx `client_max_body_size`). Include `storage/app/private` in backups.

### Birthday celebrations

Every morning at 6:45 (`birthdays:celebrate`), members whose birthday is today are celebrated automatically:
- **Card**: their card is made from the media unit's template.
- **Community**: for members with an account, the church posts the card in the General room ("Today is Ada's birthday! … reply with your wishes and prayers"), shown as a birthday post from the church. Members reply and react as usual; replies notify the celebrant's phone. The celebrant can remove the post, and the media HOD can switch community posts off.
- **Their account**: a "Happy birthday" banner with their card for three days, buttons to download it and share it on WhatsApp, a count of wishes and reactions, and a link to see them.
- **Messages and phone**: a birthday message in their inbox, and a push notification if they installed the app.
- **Tokens**: the birthday tokens, as before.

Members add a photo for their card, and can switch public celebration off, in My account › My birthday card; they can preview their card there at any time. Members who switch it off aren't posted or listed in the community birthdays, and the media unit sees "Prefers not to be shared". 29 February birthdays are celebrated on the 28th in other years.

## Prayer requests

Anyone can send a prayer request from the form on the home page, as before. Members can also send them from **My account › Prayer**:
- **Choices:** they pick what it's about (health, family, finances, work, spiritual growth, direction, grief, thanksgiving or something else) and who can see it. That's only the pastors (private), the pastors and prayer team, or the church too, on the members' prayer wall, where they can choose not to show their name.
- **Replies:** the prayer team (the "Prayer requests" area) sees members' requests on admin › Prayer requests, with filters for "From members" and "On the prayer wall". They can reply with a word of encouragement or a scripture. The member gets it by email, on their phone, and on their prayer page, and the request shows as prayed for. Requests from the home page have no account to reply to, so the team follows up with the contact details left.
- **Answered:** members tell the church when God answers, with what happened, and are invited to share it as a testimony (the testimony form starts with their request and answer).
- **The prayer wall** is for approved members only. It shows shared requests from the last 60 days (first name, or no name), and members tap "I prayed" once each so the person knows how many prayed. Staff can take a request off the wall. Members can remove their own requests.

## Message centre (simple CRM)

Admin › **Message centre**, for the pastors and the church office (the "Message centre" area):
- **Contacts:** everyone the church can reach in one searchable list: members (every status), approved sellers and staff. It shows each person's email, phone and WhatsApp link (or "WhatsApp off" if they turned it off), and how many messages they've had.
- **New message:**
  - **Who it's for:** members (narrowed by approval status, role (workers, ministers or both), unit, house fellowship, gender or birthday month), sellers (all approved, listed now, or lapsed), staff, or everyone. A live count shows how many people it reaches, and how many each way. Each person gets it once, even if they're both a member and a seller.
  - **The message:** a subject and message with fill-ins `{first_name}`, `{name}` and `{church}`.
  - **Send by:** email (ticked to start), WhatsApp and/or app notification, whichever are switched on in Settings & roles › Notifications.
  - **When:** send now, schedule for later, send yourself a test, or save a draft.
- **After sending:** messages go out in batches of 80 a minute through the cron job, so a big list never times out on shared hosting. Members also find them in Messages in the app. Each message's page shows progress, how many were reached each way, and who couldn't be reached and why (no email, WhatsApp number or app), so the office can call them. A scheduled message, or one still going out, can be stopped.

## Reminders and greetings

The app sends these by itself. Staff with the "Reminders & greetings" area (pastors and the church office to start with) manage them on **admin › Reminders & greetings**:
- **Service reminder:** before each service on the Attendance page (2 hours before to start), to every approved member.
- **Thank-you greeting:** after the service ends (30 minutes after to start), to the members who checked in or were marked present.
- **Feedback request:** a little later (3 hours after to start), to the members who were there and haven't said how it was. It opens Feedback in their account.
- **Market renewal reminder:** sellers are reminded before their subscription ends (7 days before to start). Optionally they also get a last reminder the day before, and a note when it has ended and their shop is hidden. This replaces the old fixed email.

For each one, staff can:
- switch it on or off and set when it goes;
- choose email (ticked to start), WhatsApp (only to members who haven't turned it off) and/or app notification;
- write the wording, using fill-ins like `{first_name}`, `{service}`, `{day}`, `{time}`, `{shop}` and `{end_date}`, with a live "How it reads" preview;
- send themselves a test.

Service messages also appear in each member's Messages. The page shows what's coming up, which subscriptions end soon, and what was sent recently. Each service gets each message once, and only recent services count, so switching one on never messages old services. `reminders:send` runs every five minutes from the scheduler; `market:remind` runs daily at 8am.

## Feedback and suggestions

Members give feedback from **My account › Feedback**:
- **On services** held in the last 7 days: an overall rating (poor to excellent), what went well, and what could be better. The parts to pick from are worship, the sermon, prayer, welcome and ushering, sound and screens, the live stream, timing, the venue and children's church. They can add a comment and leave their name off. Sending again updates their feedback.
  - **Nudge:** for three days after a service a member checked in to, their account page asks "How was Sunday service?".
- **Suggestions** on anything (services, events, units, welfare, youth, facilities, the app, communication or something else), with or without their name. Members see where each suggestion is up to (sent, being looked at, we're doing it, done, or not for now) and the church's answer.

The pastors and church office (the "Feedback" area) see it on **admin › Feedback & suggestions**:
- **Each service:** the average rating, how many responded, what was praised and what was flagged most, the spread of ratings, and the comments.
- **Suggestions:** an inbox, filtered by where each one is up to. Staff set the status and write an answer; the member is emailed and notified on their phone when there's a new answer.
- **Names:** anything sent without a name shows as "A member (no name)".

## Visits with a pastor

Members book a visit from **My account › See a pastor**:
- **Open times** come from each pastor's weekly visiting hours. The pastor or the church office sets these on admin › Visits with a pastor › Visiting hours (for example Tuesdays 10:00 to 13:00 in 30-minute visits, in person or by call, and where). Times are open up to 21 days ahead, with at least 12 hours' notice (`church.appointments.notice_hours`). Time off, and visits already booked, are taken out.
- **Booking:** the member picks a pastor (when there's more than one), a time, in person or by call, and what it's about. They can add a note that only that pastor and the pastor in charge can read; the office sees that there is a note, not what it says. A member can have two visits waiting or coming up at once. Two people can't take the same time.
- **The pastor or office** confirms it (with a message such as where to come, and a call link for calls), moves it to another time (clashes are refused), or declines it with a reason. The member is told by email and on their phone. Afterwards they mark whether it happened.
- **Reminders:** at 5pm (`appointments:remind`), members get a reminder of the next day's visits, and each pastor gets a list of the people they're seeing.
- Members can cancel a visit that hasn't happened, and the pastor is told.

Pastors open the page on their own visits; the office sees everyone's, with a filter by pastor. Who can manage this is set in Settings & roles (the "Visits with a pastor" area: pastors and the church office to start with).

## The church library and book reviews

Members borrow books from **`/library`** (also on their account page):
- **Finding a book:** search by title or author, or browse by category (Christian living, devotionals, Bible study, prayer, biographies, marriage and family, leadership, money and career, youth, children, fiction). Each book shows whether a copy is free, its rating, and where it's kept. Books without a cover photo get a drawn cover in colour.
- **Reserving:** if a copy is free, it's set aside to collect at church within 7 days. If not, the member joins the waiting list and is told by email and on their phone when a copy is ready for them. They can cancel any time before collecting.
- **Borrowing:** loans last 14 days. Members can renew once from the library page, unless the book is late or someone is waiting for it. A member can have up to 3 books at a time, counting reservations, and can't reserve more while a book is overdue.
- **Reminders:** each morning (`library:remind`), members are reminded two days before a book is due, and weekly while it's late. Reservations not collected within 7 days are released and offered to the next person waiting.
- **Tokens:** bringing a book back by its due date earns 5 tokens, and a member's first review of each book earns 2. The pastor in charge sets both amounts on the Token treasury page.

**Book reviews** are in the community (`/community/book-reviews`) and on each book's page. A member gives 1 to 5 stars and says what they thought; writing again updates their review. The community home shows the latest reviews. Members can delete their own reviews, and moderators can take any review down. Members muted in the community can't post reviews.

**The library desk** is admin › Library, for the librarian, pastors and the church office (the "Library" area; there's a **Librarian** role for this alone):
- **Tabs:** books to hand over, on loan, overdue (with how many days late), the waiting list, and history, with a search by member, phone or book.
- **Actions:** hand a book over (the 14 days start then), mark it returned (the next person waiting is told), give more time (1, 2 or 4 weeks), or cancel a reservation.
- **Books:** add and edit books with the number of copies, the shelf, an ISBN and a cover photo. Turning a book off hides it without losing its history. Copies can't go below the number out on loan.

The limits are in `config/church.php` under `library` (`loan_days`, `max_books`, `renewals`, `collect_days`).

## Testimonies

Members share a testimony from **My account › Testimony**:
- **What they give:** a title, the kind (healing, provision, breakthrough, salvation, deliverance, family, career, protection or other), and the story.
- **Their choices:** how their name is shown (full name, first name only, or none), whether it can go on the website, and whether it may be read out in a service.
- **Review:** a pastor, the office or the media team (the "Testimonies" area) reads it on admin › Testimonies. They can tidy the spelling, then publish it or decline it with a kind note the member sees. Published testimonies can be taken down again.
- **Publishing:** the member is emailed, and earns tokens (5 to start, once a month at most; the pastor in charge sets the amount on the Token treasury page). The testimony appears on `/testimonies` only if the member agreed to the website. Signed-in people can say "Praise God" once each, and the testimony can be shared to WhatsApp.
- Members can withdraw a testimony at any time.

### Online shops

Every seller has their own online shop at `/market/shops/{their-shop}` (no custom domains), which they manage themselves at `/vendor`:
- **Home:** where sellers land after signing in: quick actions (add a product, add a service, design my shop, share my shop), a **getting-started checklist** that ticks itself (logo, banner, tagline and story, first listing, a photo on every listing, subscribe, share), share buttons for the shop, figures (live listings, new enquiries, visits from shares, Crown orders) and the latest enquiries.
- **Help and tours:** every seller page has a Help button with what the page is for and common questions, and a guided tour (offered the first time a page is opened, and from Help any time).
- **My online shop:** a wide **banner** and a **logo** (resized for the web). Until the seller adds their own, the shop has a **designed default banner** (their colour with a soft crown pattern, their name and tagline) and a **default logo** (their initials with a small crown, in their colour), used everywhere the shop appears; a **shop colour** from eight (buttons, sections and badges follow it), a **tagline**, an **announcement** bar (a sale, holiday hours), **their story**, **opening hours**, **where to find them** (with a map link), **delivery** and **returns** terms, and **Instagram, TikTok, Facebook and website** links. A preview at the top shows the banner, logo, colour and tagline as they're chosen.
- **Sections:** the seller groups listings into sections such as "Cakes" or "Drinks", renames and orders them, or removes one (its listings stay, with no section).
- **Products and services:** up to **5 photos** each (pick several at once; large photos are made smaller and get a thumbnail). The seller chooses the cover, removes photos and adds more. Each listing can have a section, a **"was" price** (shown crossed out with the % saved), be **featured** at the top of the shop, be hidden and shown again in one tap, or be deleted (enquiries about it are kept; not while a Crown order for it is waiting).
- **What buyers see:** the announcement bar, the banner with logo, name, tagline, hours and buttons to WhatsApp, call or read about the shop; share buttons; section chips, a search box and sorting (newest, price low to high, high to low); Featured first, then each section, then everything else; and an About panel with the story, hours, location, delivery, returns, contact and social links. Product pages have a photo gallery (swipe on phones; arrows, thumbnails and arrow keys on computers), the sale price, the section, a "Sold by" card in the shop's colour with its logo, and the shop's delivery and returns terms.
- The market's shop list shows each shop's logo and tagline.
- **Sharing:** the shop page and every listing have share buttons (WhatsApp, Facebook, X, Telegram, copy link, and the phone's share sheet). Sellers share from Home, from Share (pictures for posts and status, captions and QR codes), and right after adding a listing, which offers to share it there and then.
- Every market and seller page works on phones and tablets: sections scroll sideways, photos sit two to a row, and quick actions and checklists fit small screens.

### Sharing shops and listings

Sellers have a **Share** page in their shop area (and a Share button on each listing):
- **Pictures, drawn by the app** for the shop and every listing: a square post (Instagram, Facebook), a tall one for WhatsApp status and stories, and a wide link preview. Each has the photo (a shop shows up to four of its listings), the name, the price or category, the shop, the market's name and a QR code that opens the page. They're redrawn when the listing changes.
- **A ready-written caption**, with the name, price, a short description and the link. They can edit it before copying.
- **One-tap sharing** to WhatsApp, Facebook, X and Telegram, and copy link. On phones, "Share…" opens the phone's share sheet with the picture attached, so it goes straight into WhatsApp status or Instagram.
- **Downloads** of each picture, and a QR code for flyers and stalls.
- **Visits from shared links:** each shared link is marked with where it was shared (`?s=wa`, `fb`, `x`, `tg`, `qr`…). Visits are counted once per visitor, never the seller's own, and shown on the Share page.

Shop and listing pages also have proper link previews (photo, price and name show when the link is pasted into WhatsApp or Facebook), and share buttons for anyone who wants to pass them on. The naira sign uses the Anton font, as Poppins doesn't have it.

### Map and directions

The church's address (admin › Settings & roles › Church details) drives everything location-related:
- **About page:** a live Google map, plus "Get directions" and "Open in Google Maps" buttons. "Get directions" opens turn-by-turn directions from wherever the visitor is.
- **Elsewhere:** the Contact page also has "Get directions"; the footer address and the "Next gathering" card on the home slider link to the map.

No Google API key is needed. If Google puts the pin in the wrong place, paste a shared Google Maps link into "Directions link" and an embed link (Google Maps › Share › Embed a map) into "Map embed link"; they're used instead of the address. The starting address is set in `config/church.php`.

## Money: giving, tickets, the market and the notice board

All online payments go through Paystack and are recorded in one `payments` table (amounts in kobo). A payment only counts once Paystack confirms the amount and currency, either when the buyer returns or through the signed webhook at `/webhooks/paystack`; the same confirmation arriving twice changes nothing.

**Who gets what.** Each deployment runs on Donaitech's Paystack integration with the church as a subaccount, so:

| Payment | Church | Donaitech |
| --- | --- | --- |
| Gift (tithe, offering, appeals) | the gift | `PLATFORM_FEE_GIVING`% |
| Event ticket | the ticket price | `PLATFORM_FEE_TICKETS`% |
| Notice board listing | the package price | `PLATFORM_FEE_NOTICES`% |
| Seller subscription | the plan price, after any coupon | `PLATFORM_FEE_MARKET`% |

Market sales themselves never go through the site: buyers pay sellers directly.

Paystack divides each payment at the moment it's made, so nothing is paid out by hand. Settings: `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SUBACCOUNT` (the church), the `PLATFORM_FEE_*` percentages, notice prices in `config/notices.php`, and the market's subscription periods and coupon percentages in `config/market.php`. In the Paystack dashboard, point the webhook to `https://<site>/webhooks/paystack`.

**Event tickets** (admin › Events & tickets): ticket types per event, free or paid, with optional limits. Unpaid orders hold their places for 30 minutes. Every attendee gets a QR code; scanning it opens the door check-in page for signed-in staff.

**Market** (admin › Tribe Market): members apply to open a shop, and approving emails them a link to set a password. At `/vendor`, sellers list products and services (priced fixed or "per hour", "per session" and so on), read buyer enquiries, and pay a subscription to be listed: monthly, quarterly, 6 months, 1 year or 3 years. The admin prices each period (a plan is only offered once it's priced and switched on) and creates coupons for 10%, 20%, 50% or 100% off, with optional use limits and end dates. A 100% coupon activates with no payment. Renewing early adds the new period after the current one. A shop's listings show only while it is approved and subscribed. Buyers contact the seller by phone, WhatsApp, email or an enquiry form (with a date for services), and arrange payment with them.

**Notice board** (admin › Notice board): other churches and businesses pay to list a notice; nothing appears until an admin approves it, and it runs for the days paid for. Rejected notices are refunded from the Paystack dashboard.

**Giving** (admin › Giving): the Give page takes one-off gifts to any active fund (tithe, offering, building fund, missions and welfare to start with, from `config/finance.php`). A fund with a target shows its progress on the Give page. Cash, cheques and transfers are recorded by finance staff. Recurring monthly giving isn't offered yet; it would need Paystack's subscription plans.

**Tokens are not money.** Members never pay for tokens or to use the platform; the only things paid for with money are the market subscriptions, tickets, notice board listings and donations. Tokens are free rewards (see "Tokens" below).

Emails (receipts, tickets, reminders, welcome links) use Laravel's mailer: set `MAIL_*` in `.env`. Uploaded pictures go straight into `public/uploads` (the `public` disk), where the web server sends them itself: no `storage:link` shortcut is needed. That folder's `.htaccess` stops anything uploaded from running as a script or opening as a web page. Private files (expense receipts, welfare documents, the media file library) stay out of reach on the `local` disk in `storage/app/private`.

## Going live

What the church or Donaitech needs to do; none of it can be done from the code.

**1. A server.** Shared hosting (cPanel, DirectAdmin, Hostinger, Namecheap, Whogohost, Truehost and the like) is fine, as is a VPS. It needs PHP 8.3 or newer and a MySQL/MariaDB database. Turn on free SSL (AutoSSL / Let's Encrypt) for the domain.

**2. Install on shared hosting (cPanel).** No Node.js is needed on the server: the built CSS and JavaScript are already in `public/build`.

1. **PHP:** in *Select PHP Version* (or *MultiPHP Manager*), choose **PHP 8.3+** and tick the extensions `mbstring`, `openssl`, `pdo_mysql`, `curl`, `gd`, `fileinfo`, `xml`, `dom`, `ctype`, `tokenizer` (and `exif`, `intl`, `gmp` if offered). Under *Options*, set `memory_limit = 256M`, `upload_max_filesize = 16M`, `post_max_size = 200M`, `max_file_uploads = 40`, `max_execution_time = 60`.
2. **Database:** in *MySQL Databases*, create a database and a user, and give the user all privileges on it.
3. **Upload the code.** Either use *Git Version Control* to clone the repository, or upload a zip and extract it. Then, if your host has *Terminal* or SSH, run `composer install --no-dev --optimize-autoloader` in the project folder. Without SSH, run that on your own computer and upload the `vendor` folder too.
4. **Point the domain at `public/`.** Best: in *Domains*, set the domain's document root to the project's `public` folder (e.g. `/home/you/theembassy/public`). If your host can't change it and serves `public_html`, put the whole project in `public_html`: the included `.htaccess` sends every request into `public/`, so nothing else can be downloaded.
5. **Settings:** copy `.env.shared-hosting.example` to `.env` (File Manager › Copy; it has the live-site defaults) and fill in `APP_URL=https://your-domain`, `APP_ENV=production`, `APP_DEBUG=false`, the `DB_*` details, `MAIL_*` (step 4 below), and the `CHURCH_*` and `PAYSTACK_*` values.
6. **Set up**, in *Terminal* (or ask the host to run these once):

```sh
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # starting units, funds and market periods
php artisan admin:create you@church.org --name="Your name"
php artisan admin:create pastor@church.org --name="Pastor's name" --super   # the pastor in charge
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan app:check                # lists anything the server is still missing
```

`storage/`, `bootstrap/cache/` and `public/uploads/` must be writable (755 or 775). Uploaded pictures are saved in `public/uploads`; keep its `.htaccess` file, which stops uploads from running as scripts. On nginx, add the same protection: `location ^~ /uploads/ { location ~* \.(php\d?|phtml|phar|html?|js)$ { deny all; } }`. After changing `.env` later, run `php artisan config:cache` again. **Admin › Settings & roles › Server check** shows the same checklist in the browser, with how to fix each item in cPanel.

**Updating** after new code is pushed: pull it (Git Version Control › *Update from Remote*), then run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force` and `php artisan optimize`.

**On a VPS** it's the same, plus `npm ci && npm run build` only if you change the CSS or JavaScript.

**3. The scheduler (cron job).** Reminders, the weekly email, announcements sent in batches, YouTube syncing, social posts, birthday wishes and the daily follow-ups all run from one cron job. In cPanel › *Cron Jobs*, add one set to **once per minute** (`* * * * *`):

```
cd /home/YOUR-USER/PATH-TO-SITE && php artisan schedule:run >> /dev/null 2>&1
```

Use the full path to PHP if the host asks for it (e.g. `/usr/local/bin/php` or `/opt/cpanel/ea-php83/root/usr/bin/php`). If your host only allows every 5 or 15 minutes, that's fine: everything still runs, a little later (all the daily times fall on the quarter hour). The jobs run inside that one PHP process, so hosts that switch off `proc_open` are fine too. The Server check page shows when the cron job last ran.

**4. Email.** On shared hosting, create a mailbox in cPanel › *Email Accounts* (e.g. `no-reply@your-domain`) and use its details: `MAIL_MAILER=smtp`, `MAIL_HOST=mail.your-domain`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`, `MAIL_USERNAME` (the full address), `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` (the same address). A sending service (Zoho, Brevo, Mailgun, Postmark, Amazon SES) works the same way and lands in inboxes more reliably for large lists. Until then nothing is emailed: receipts, password links and reminders.

**5. Paystack.** In the Paystack dashboard: create the church's subaccount and put its code in `PAYSTACK_SUBACCOUNT`; copy the live secret and public keys into `PAYSTACK_SECRET_KEY` and `PAYSTACK_PUBLIC_KEY`; set the webhook URL to `https://<site>/webhooks/paystack`. Until the keys are set, the site shows bank transfer details instead of taking payments. Paystack is only used for donations, tokens, tickets, the market and the notice board; tithes and offerings are never taken through it.

**6. In admin.** Sign in at `/admin`, then:
- **Settings & roles**: fill in the address, service times, social links and church email, and add the other staff.
- **Giving › Bank accounts**: add the church's accounts in each currency people give in.
- **Pages**: write the church's story, add the leaders' names and photos, and check the beliefs and first-timer questions.
- **Blog**: replace the starter articles (their author and body are `[placeholders]`) or delete them.
- **Tribe Market**: price the subscription periods and switch them on.
- **Giving**: check the funds.
- **Token treasury** (pastor in charge): create the first tokens into the treasury, and check how many each activity earns. Until tokens are created, nobody earns any.
- **Sermons & live**: add the first messages and the current series.
- **Members**: import the existing register from a spreadsheet.

**7. YouTube (optional).** In Google Cloud: create a project, enable the YouTube Data API v3, set up the OAuth consent screen (External), and create an OAuth client of type "Web application" with `https://<site>/youtube/callback` as an authorised redirect URI. Put the client ID and secret in Settings & roles › YouTube broadcasts (or `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`), then the media HOD connects the channel from Service setup & streaming. While the consent screen is in "Testing", Google ends the sign-in after 7 days and the page will ask to connect again; publish the app (Google reviews the YouTube permission, which can take a few weeks) for a lasting connection. Live streaming must be turned on for the channel in YouTube Studio.

**8. Notifications: email, WhatsApp and app notifications.** Admin › Settings & roles › Notifications has three church-wide switches. **Email** is on to start and is the default for every notification: reminders, updates about visits, library books, testimonies, prayer replies, suggestion answers, birthdays and announcements. Receipts, tickets and password emails always go. **WhatsApp** is off until it's set up. **App notifications** are free and can be switched off. Text messages are not used, to save fees.
- WhatsApp: create a WhatsApp Business account and app on Meta for Developers, add the church's number, and create a message template in WhatsApp Manager (category Utility, e.g. named `church_notice`) whose body is just `{{1}}`. Once Meta approves it, put a permanent access token, the phone number ID, the template name and its language code in Settings & roles › Notifications, and switch WhatsApp on. Meta charges per message the church starts, much less than SMS. Members can turn WhatsApp off in their account.
- App notifications: run `php artisan push:keys` once on the server (it saves the keys in Settings). Installing PHP's `gmp` extension makes sending them much faster. Notifications need HTTPS.

**9. Uploads.** Gallery uploads send several photos at once, so raise PHP's limits to match (step 2): `upload_max_filesize = 16M`, `post_max_size = 200M`, `max_file_uploads = 40`, and `memory_limit = 256M` for resizing large photos (plus `client_max_body_size 200m;` on nginx). The upload page tells staff to send fewer photos if a limit is hit. Photos too big for the server's memory are refused with a clear message, and where the host's image library can't make WebP files, pictures are saved as JPEG.

**10. Search engines.** Submit `https://<site>/sitemap.xml` in Google Search Console.

**11. Backups.** Back up the database daily, plus `public/uploads` (uploaded photos) and `storage/app/private` (receipts and private files).

### Deploying for another church

Repeat the steps above on a new server, with the new church's `CHURCH_*` and `BRAND_*` values in `.env` and its logo in `public/images/`. Everything else (address, bank accounts, leaders, links, staff) is set in admin, so the code doesn't change between churches.

## Youth empowerment

Young people apply for empowerment programmes at **`/empowerment`** (linked in the footer under Get involved). Admin › **Youth empowerment** is for the pastors and the church office (the "empowerment" area):
- **Programmes:** start one with a name; it begins as a draft with ages 16 to 35, two choices each and four sample questions, all changeable. The public page shows the open programme (or the latest published one); each programme also has its own address.
- **The page:** a **banner** that can be changed any time (the old picture is removed; a designed banner shows until one is added), a name, tagline and about text, when applications open and close, an age range (applicants give their date of birth and anyone outside it is told kindly), how many choices each person picks (one, or a ranked first, second and third), a thank-you line for after they apply, and Published.
- **Choices:** the empowerment options people pick from (e.g. Catering and baking, Fashion design, Phone repair), each with what they'll learn and optional **places**; the page shows how many are left, and a full choice can still be picked for the waiting list. Choices can be reordered, switched off or removed (ones already picked are switched off, so applications stay whole). A programme can't be published without at least one choice.
- **The form:** name, phone, email (optional), gender, date of birth, where they live and their choices are always asked. Admins add their own questions: short answer, paragraph, number, date, dropdown, multiple choice, checkboxes or yes/no, each with a hint, required or optional, in any order.
- **Applying:** members' details are filled in when they're logged in. Each application gets a reference (EMP-…), a confirmation email (and WhatsApp if it's on) with a private link to see its status, and the thank-you shows on the page. One application per phone number per programme; a hidden field stops bots.
- **Reviewing:** applications by status (new, shortlisted, accepted, waiting list, not selected, completed) and by choice, with search; how many picked and were accepted for each choice. Open one to see every answer, set its status, choose what they're accepted for, keep a private note, and let them know (a message for each status). Tick several to change them together. **Download Excel** gives every application with each answer in its own column, plus a summary. Reports › Youth empowerment covers all programmes for any dates.
- **Demo:** `php artisan db:seed --class=EmpowermentDemoSeeder` adds an open programme with six choices, five questions and 24 applications at different stages.

## Member logins

Members sign in at `/login` with their **email or phone number** (written any way: 0803…, +234 803…) and a password. **"Keep me signed in"** is ticked to start and lasts **90 days** on that phone (`AUTH_REMEMBER_DAYS` in `.env` changes it), and people who join online stay signed in too.

Members the office adds (Add member, Register someone, or a spreadsheet import) get a login from their profile in admin, under **Login**, or straight away from the form:
- **Send a link to set a password:** emailed (and sent on WhatsApp when it's on). It works for **7 days** and can be sent again any time; the profile shows when it was sent and when they last signed in.
- **Give a temporary password:** shown to the staff member once (with Copy and **Send on WhatsApp**) to pass on. It works for members with only a phone number. The member must choose their own password the first time they sign in, before anything else.
- **Send links to everyone:** the members list shows how many members with an email have no login, with one button to send them all a link, and a "No login yet" filter.

Members change their password from their account page ("Change password"); a new password signs out "keep me signed in" on their other devices. There's no shared default password: anyone who knew it could sign in as any member, so each temporary password is different and must be changed.

## Phones first

Most people use the app on a phone, so every page is built for one and checked at 360px wide (and on tablets): nothing scrolls sideways or runs off the screen. On phones and touch screens:
- form boxes use 16px text, so iPhones don't zoom in when one is tapped;
- small controls get a finger-sized tap area: checkbox rows, "Done / Not yet" buttons, show-more toggles, the ⓘ tips and chat message actions;
- status labels and small badges stay at least 12px;
- the busiest admin lists (members, unit members, empowerment applications, contacts) turn into cards, each value labelled with its column; other wide tables scroll inside their box, with a soft fade at the edge to show there's more;
- the announcement bar at the top is one compact, tappable row, and admin pages leave room so the floating Help button never covers the last item.

## Bible verses

Members find **Verses** on their account page: one verse fills the screen, and swiping up (or tapping, or the down arrow on a computer) brings another, picked at random without repeats until they've seen them all. Each verse can be **saved** (kept under *My saved verses*), **shared** (the phone's share sheet, or WhatsApp on a computer) or **copied**. Once a day, the first time a member opens the app, a **verse for today** pops up with "Amen" to close it; they switch it off or on in *My details*. The verses are the King James Version, which is free to use (modern versions need the publisher's permission). About 350 hand-picked verses come with the app, grouped by theme (comfort, faith, strength, provision, healing and so on), from `database/data/verses.php`; the church office, pastors and media team add, edit or hide verses in admin › **Bible verses**.

## Workers' permission

Workers (and ministers, and anyone serving in a unit) find **Permission** on their account page. They say whether they won't be in church or will be coming late (and about when), pick the service (or a day that isn't listed), and give their reason. The HOD and assistant of every unit they serve in, and the pastors, are told at once by app notification, WhatsApp and email. The request then appears under **Permission** in each HOD's unit area and under **Workers' permission** in the church admin. Only the pastor in charge, administrators and pastors see them there, not the church office; which roles cover the area is set in `config/roles.php`. Either the HOD or a pastor can grant or decline it with a short note, and change their mind until the day; the worker hears back each time. If the worker is on the rota for that service, the request says so, so someone can arrange cover. A Sunday a worker asked to miss doesn't count towards a "we missed you" follow-up.

## Names and gender

Members and first-timers have a first name and a last name, and gender (female or male) is required on every form that adds or edits one: joining, the welcome card, Register someone, first-timer cards and the member editor. The full `name` is still stored and kept in step (`App\Models\Concerns\HasFirstAndLastName`), so lists, search and messages keep working; `App\Http\Middleware\CombineName` joins the two boxes for the controllers. People already on the register were split with the last word as the last name ("Sis. Adaeze Obi" became "Sis. Adaeze" and "Obi"); fix any odd ones in the member editor. A member list CSV can have `first_name` and `last_name` columns, or one `name` column.

## Birthdays

Birthday boxes ask for the day and the month; the year is optional. Under them: "For birthday wishes from the church. The year is optional." Without a year, the date is saved with the year 1904 (`Member::NO_BIRTH_YEAR`, a leap year so 29 February works), and pages show just "15 November". `$member->birthdayLabel()` and `birthdayYearKnown()` give the right wording. The boxes are `<x-birthday-input>`; `App\Http\Middleware\CombineBirthday` joins them into `birthday` before the controllers see the form. A member list CSV can give a full date or just "15/11".

## Pop-up messages and questions

Every "saved", "sent" or "something went wrong" message pops up as a toast, using [Laravel Notify](https://github.com/mckenziearts/laravel-notify). They come in at the top of the screen, across the full width on a phone and in the top-right corner on a computer. Green means done, red means a form needs fixing (with the first problem spelled out). They close on their own after a few seconds, or with the ×.

- `resources/views/partials/notify.blade.php` is included by every layout. It turns `->with('status', '...')` and form errors into toasts, so controllers don't need to change.
- New code can call the package directly: `notify()->success()->title('Saved')->message('...')->send();` (also `error()`, `warning()` and `info()`).
- The package's CSS and JS live in `public/notify` (not the package's usual `public/vendor`, which would hide the seller area at /vendor) and are committed, because shared hosting can't run `vendor:publish`. They only load on pages that show a message. Colours are set in `resources/css/base.css` under "Pop-up messages".
- Thank-you panels that replace a form, like "Message sent" on the contact page, stay on the page as before.

Questions like "Remove this member?" or "Give Seun a new temporary password?" open the app's own box instead of the browser's grey one. It is a card in the middle of a computer screen and a sheet from the bottom on a phone, with big buttons. The OK button repeats the action ("Give a temporary password"), and it turns red for anything that deletes, removes or cancels. Add `data-confirm="Question? Optional detail."` to any form; in JavaScript, `await ask({ title, text, ok, input })` from `resources/js/dialog.js` replaces `confirm()` and `prompt()`. A test fails if `confirm(`, `prompt(` or `alert(` comes back.

## Reports (Excel)

Admin › **Reports**, for administrators and the pastor in charge only (nobody else sees the page or can download). Choose the dates (or This month, Last month, This quarter, This year, Last year) and download a report as an Excel workbook (`.xlsx`). Each has a **Summary** sheet with the key figures, then sheets with every record behind them; header rows stay in view, every column can be filtered and sorted, money is in naira and dates are real dates Excel can sort. Text is stored as text, so nothing in a cell can run as a formula.

| Report | Summary | Sheets |
| --- | --- | --- |
| Members | totals by status, role, gender, training, unit and fellowship; new members in the dates | Members |
| Attendance | services, average and highest headcount, check-ins, first-timers, members who came and didn't | Services, Members (attendance rate) |
| Follow-up & visitation | first-timers, follow-ups by reason and outcome, days to complete, visits | First-timers, Follow-ups, Visits |
| Units | heads, workers, ministers, meetings, requests to join | Units, Unit members |
| Giving (pastor in charge only) | total, by fund, method and month | Gifts |
| Income & expenses | income, expenses, balance, pending, by category | Income, Expenses |
| Welfare | cases, amounts requested and approved, by category; package applications | Cases, Package applications |
| Tribe Market | shops, subscriptions, money and Crowns paid, discounts | Shops, Subscriptions |
| Events & tickets | tickets, check-ins, money taken | Events, Orders |
| Library | lent, returned, late, overdue, most borrowed | Loans |
| Crowns | movements by kind | Movements |
| Message centre | messages, people reached each way | Messages |
| Feedback, testimonies & appointments | average rating, suggestions, testimonies, appointments by status | Service ratings, Suggestions, Testimonies, Appointments |

Workbooks are made by the app itself (no extra package), using PHP's `zip` extension, which shared hosts include; Settings & roles › Server check shows whether it's on.

## Running it

### On your computer

**1. Install the tools** (once):
- **PHP 8.3 or newer**, with the extensions `gd` (with FreeType, JPEG and WebP), `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `fileinfo`, `intl`, `dom` and `zip`. Optional: `exif`, so phone photos come out the right way up.
- **Composer** (PHP's package manager).
- **Node.js 20.19+ or 22.12+**, which comes with npm.
- **Git**.

Easiest per system:
- **macOS:** [Laravel Herd](https://herd.laravel.com) (PHP and Composer in one), then Node from nodejs.org. Or with Homebrew: `brew install php composer node git`.
- **Windows:** [Laravel Herd](https://herd.laravel.com) for PHP and Composer, Node from nodejs.org, and Git from git-scm.com.
- **Ubuntu/Debian:** `sudo apt install php8.3-cli php8.3-gd php8.3-sqlite3 php8.3-mbstring php8.3-curl php8.3-xml php8.3-intl php8.3-zip unzip git`, then Composer from getcomposer.org and Node from nodejs.org (or nvm).

Check with `php -v`, `composer -V`, `node -v` and `php -m` (look for `gd` and `pdo_sqlite`).

**2. Get the code and set it up:**

```sh
git clone https://github.com/samowolabi1/TheEmbassy.git
cd TheEmbassy
git checkout claude/royal-tribe-embassy-design-h9ecd1   # until it's merged into main
composer setup        # PHP and npm packages, .env, the SQLite database, tables, the uploads link, and the built CSS/JS
php artisan db:seed   # starting content: announcements, events, units, funds, plans and more
php artisan admin:create you@example.com --name="Your Name" --super   # your admin login (prints a password)
```

**3. Run it:**

```sh
php artisan serve     # the site at http://localhost:8000; admin at http://localhost:8000/admin/login
```

Optional, each in its own terminal:

```sh
npm run dev               # live reload while you change CSS or JavaScript
php artisan schedule:work # the timed jobs: birthdays, reminders, sending announcements, YouTube sync…
```

**Demo members** (optional): `php artisan db:seed --class=MemberDemoSeeder` adds 36 members:
- **Who:** 30 approved, 4 waiting for approval and 2 inactive. Each has a full profile (phone, address, birthday, milestones, some wedding anniversaries), and most are in units and house fellowships.
- **Unit heads:** each unit gets a head and an assistant.
- **Attendance:** the last 8 Sundays and 4 midweek services, with check-ins and the tokens they earn. A few members haven't been seen for three weeks, so the care list has follow-ups.
- **Birthdays:** member1 has a birthday today (run `php artisan birthdays:celebrate` to see the celebration), and a few more fall this week and this month.
- **Activity:** first-timers, prayer requests (some on the prayer wall), testimonies (two published, one to review), feedback on last Sunday and three suggestions.

Members sign in at `/login` as `member1@demo.test` to `member36@demo.test` with the password `demo-password`. It's safe to run again, and it refuses to run when `APP_ENV=production`.

**Demo events** (optional): `php artisan db:seed --class=EventDemoSeeder` adds 10 events, dated from today so they stay current:
- **Tribe Fest**, a featured convention with paid ticket tiers (an early bird that has sold out, regular, VIP and a group ticket).
- **A couples' dinner** with paid seats.
- **A career masterclass** whose early bird has sold out.
- **Free events with limited places:** a campus Bible study, a worship night and a children's Christmas party.
- **The crossover service**, which needs no booking.
- **Two past events** with door check-ins.
- **A draft** that isn't on the website yet.

Bookings come from the demo members when they're there, so run `MemberDemoSeeder` first. Paid bookings come with their payments, and a couple of bookings were never paid. Paid tickets can't be bought on a local copy until Paystack keys are set, but free bookings work. It's safe to run again, and it refuses to run when `APP_ENV=production`.

**Demo blog** (optional): `php artisan db:seed --class=BlogDemoSeeder` adds 12 articles alongside the design's starter ones:
- **Published:** ten articles across every topic (faith, relationships, career and money, campus life, mental wellness and purpose), by a pastor and three members, with headings, lists and scripture quotes. Seven have generated cover pictures; the rest use colours.
- **Not yet public:** one draft, and one scheduled for next week, so admin › Blog shows those states too.

It's safe to run again, and it refuses to run when `APP_ENV=production`.

**Demo market** (optional): `php artisan db:seed --class=MarketDemoSeeder` fills the Tribe Market with 8 shops and about 27 products and services. Each listing gets a generated picture and a realistic price, and some can also be bought with tokens. Plan prices, buyer enquiries and a coupon (`DEMO20`, 20% off a subscription) are included. One shop's subscription has lapsed, so it's hidden from the market, and one is waiting for an admin to approve it. The sellers sign in at `/vendor/login` as `seller1@demo.test` to `seller8@demo.test` with the password `demo-password`. It's safe to run again, and it refuses to run when `APP_ENV=production`.

**Demo library** (optional): `php artisan db:seed --class=LibraryDemoSeeder` adds 14 books across the categories. With the demo members there (run `MemberDemoSeeder` first), four books are on loan (one overdue, one due in two days), two members are waiting for the same book, two are set aside to collect, and six members have written reviews. A demo librarian runs the desk: sign in at `/admin/login` as `librarian@demo.test` with the password `demo-password`. It's safe to run again, and it refuses to run when `APP_ENV=production`.

**Demo income and expenses** (optional): `php artisan db:seed --class=MoneyDemoSeeder` fills this month and the three before it, dated from today:
- **Money in:** an offering after every Sunday and midweek service, thanksgiving, building fund pledges, a wedding hall hire each month, welfare contributions, bookstand sales, an alumni donation and youth conference takings.
- **Money out:** diesel before every Sunday, electricity, internet data for the stream, refreshments, printing, transport, welfare support, outreach, new cables and the conference costs, with budgets for this month and last.
- **Checking:** older entries are confirmed or approved, one of each was declined with a reason, and the last week's are waiting to be checked.

Four demo staff record and check them, signing in at `/admin/login` with the password `demo-password`: `finance@demo.test` (Finance, who confirms and approves), `office@demo.test` (Church office), `usher@demo.test` (Ushering) and `media@demo.test` (Media). It's safe to run again, and it refuses to run when `APP_ENV=production`.

**Trying it out:**
- **Member side:** sign up at `/join`, then approve yourself in admin › Members (waiting for approval), and sign in at `/login`.
- **Market seller:** apply at `/market/open-a-shop`, then approve the shop in admin › Tribe Market.
- **Emails** aren't sent locally (`MAIL_MAILER=log`). They're written to `storage/logs/laravel.log`, including password reset and welcome links.
- **Optional services:** Paystack, WhatsApp notifications, push notifications, YouTube and Facebook/Instagram are off until their keys are set (in `.env`, or later in admin › Settings & roles). Everything else works without them.

**Starting over:** `php artisan migrate:fresh --seed`, then `admin:create` again. **Updating** after pulling new code: `composer install && npm install && npm run build && php artisan migrate`.

**If something's wrong:**
- **Photos don't show:** check `APP_URL` matches the site's address exactly (https, with or without www), then run `php artisan config:cache`. Check that `public/uploads` exists and is writable.
- **Unstyled page, or "Vite manifest not found":** run `npm run build`.
- **`could not find driver`:** the PHP `pdo_sqlite` extension is missing.
- **Birthday cards or share pictures fail:** PHP's `gd` needs FreeType and WebP.
- **Port 8000 in use:** `php artisan serve --port=8080`, and set `APP_URL` in `.env` to match.

### Tests and code style

```sh
php artisan test
./vendor/bin/pint
```

The tests use SQLite in memory. Before a release, also run them against MySQL (or MariaDB) with the cache in the database, as on shared hosting. MySQL is stricter: it found a bug that SQLite let through, where generated pictures couldn't be stored in the cache table.

```sh
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=embassy_test DB_USERNAME=... DB_PASSWORD=... DB_URL= CACHE_STORE=database php artisan test
```

## Editing content

Most content is edited in admin: announcements, the declaration, blog articles, the About page and home testimony, the gallery, events, sermons and series, the live stream, funds, and the church's details in Settings & roles. A few things still live in config files:

- `config/church.php`: name and branding (from `.env`), navigation, the gold banner, and defaults for what Settings & roles can change
- `config/content/posts.php`: blog topics, and the starter articles copied into the database the first time migrations run
- `config/content/about.php`, `config/content/home.php`: the About page and testimony text used until someone saves the page in admin
- `config/content/sermons.php`: example messages, shown until the church adds its own
- `config/finance.php`, `config/market.php`, `config/notices.php`, `config/roles.php`: funds to start with, expense categories, market periods and coupons, notice prices, staff roles

Anything still in `[brackets]` is a placeholder waiting for real details; most disappear once Settings & roles is filled in. Photo areas are coloured placeholders until real photography is added. If you run `php artisan config:cache`, run it again after editing config files (admin settings don't need it).

## Where things are

- `routes/web.php`: all routes; `routes/console.php`: `admin:create`, `admin:revoke`, `care:scan`, `units:remind`, `social:publish`, `youtube:sync`, `market:remind`, `newsletter:queue`, `messages:send`, `push:keys`, `blog:prune-images`, `tokens:daily` and the schedule
- `app/Http/Controllers/`: public pages and forms, `Account/` (members), `Vendor/` (sellers), `Admin/` (dashboard)
- `app/Models/`: people (`User`, `Member`, `Unit`, `Fellowship`, `FirstTimer`, `Service`), money (`Payment`, `Fund`, `Gift`, `BankAccount`, `Expense`, `Budget`), tokens (`TokenAccount`, `TokenTransfer`, `TokenOrder`), events and notices (`Event`, `TicketType`, `TicketOrder`, `Ticket`, `Notice`), the market (`Shop`, `Product`, `Enquiry`, `VendorPlan`, `VendorSubscription`, `Coupon`), messaging (`NewsletterSubscriber`, `NewsletterIssue`, `Broadcast`, `PushSubscription`), community (`CommunityRoom`, `CommunityMessage`, `CommunityReaction`, `CommunityVote`, `CommunityRead`, `CommunityReport`, `Quiz`, `QuizQuestion`, `QuizAttempt`), content (`Announcement`, `Declaration`, `Post`, `PageContent`, `GalleryAlbum`, `GalleryItem`, `Sermon`, `SermonSeries`, `PrayerRequest`, `ContactMessage`, `Setting`)
- `app/Services/`: Paystack and payment settlement, shop approval and seller reminders, the sermon library, `Messaging/` (weekly email, WhatsApp, web push, reminders, the broadcaster), `Content/Markdown` (safe article rendering) and `Content/Images` (photo resizing)
- `resources/views/`: `layouts/` (site, admin, seller), `partials/`, `components/`, `pages/`, `account/`, `blog/`, `market/`, `vendor/`, `admin/`, `mail/`
- `resources/css/`: `base.css` holds the design tokens; `partials/` and `pages/` hold the rest; `admin.css` is the dashboard's own stylesheet
- `resources/js/`: mobile menu, carousel, filters, share/copy buttons, countdown, give form, gallery viewer, community chat and quiz, the app shell (install button, notifications); in admin, the article editor and gallery uploads

## Forms

Prayer requests, newsletter sign-ups, contact messages, first-timer cards, market enquiries and applications are all rate limited and include a hidden field to catch simple spam bots. Staff read them in the admin dashboard.

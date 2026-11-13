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
| Church office | Members, attendance & first-timers, follow-up & care, units, the weekly email, the market, content (announcements, blog, pages), gallery, community moderation, notices, events, prayer, messages |
| Media | Content (announcements, blog, pages), gallery, community moderation, notices, sermons & live, events, and the media unit's tools (requests, file library) |
| Ushering | Attendance & first-timers, events (door check-in) |

Create the first administrator from the command line; they add everyone else in **Settings & roles**, which emails each new person a link to set their password:

```sh
php artisan admin:create pastor@example.com --name="Pastor Ade" --super   # the pastor in charge (super admin)
php artisan admin:create office@example.com --name="Church office"      # an administrator; asks for a password, or generates one
php artisan admin:revoke pastor@example.com                       # removes staff access
```

Sign-in is limited to five wrong attempts a minute per email. The site uses Lagos time (`APP_TIMEZONE`).

## Admin dashboard

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
- **Expenses & budgets**: spending logged with a receipt, approved by someone other than the person who logged it, against monthly budgets per category; CSV export.
- **Tribe Market**: shop applications, shops and subscription status, plan prices, coupons, and subscription payments with CSV export.
- **Content & announcements**, **Notice board**, **Events & tickets**, **Prayer requests**, **Contact messages**.
- **Sending announcements**: "Send" on a live announcement texts it to active members (through Termii; members can turn texts off in their account), notifies every phone that turned on app notifications, or opens WhatsApp with the message ready to post in the church groups or channel. The page shows the SMS length and pages, how many people each channel reaches, and progress. Sending the same announcement twice in a day needs an extra tick.
- **Blog**: write articles in Markdown with a formatting toolbar (headings, bold, links, lists, scripture cards, the "reflect" box) and pictures uploaded straight into the text; a cover photo with a description for screen readers; draft, publish now or schedule for later; preview before publishing; feature one article at the top of the blog. Changing an article's address keeps the old one working (it redirects). Raw HTML and unsafe links are removed when an article is saved.
- **Pages**: the About page (headline, story and photo, mission, vision, values, leaders with photos, beliefs, first-timer questions) and the home page testimony. Units on the About page come from the units' "What they do" lines.
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

- **Rooms**: General, Prayer & praise, Word & devotion, Campus life, Career & hustle and Banter & fun to start. Staff add, rename, reorder and delete rooms. A room can be tied to a unit so only that unit's members see it, or set to "announcements only".
- **Chat**: text with clickable links, photos (resized, location data removed), replies, six reactions (🙏 ❤️ 😂 🔥 👏 🙌), and polls that close after an hour to a week. People edit their own messages for 15 minutes and delete them any time. Messages appear for everyone within a few seconds without reloading, unread counts show on each room, and people who turned on app notifications get one when someone replies to them. Everything also works without JavaScript.
- **Fun**: a weekly **Bible quiz** staff write in admin (timed questions, one go each, scored on the server, leaderboard with the fastest time breaking ties; answers are shown once it closes), and **birthdays this week** (day and month only, never the year).
- **Safety**: house rules on the community page; members report a message with a reason; moderators (the "Community" area: administrators, pastors, church office and media) keep or remove it, and can mute the writer for 1 to 30 days with a reason they see. Moderators can remove any message and post in announcements-only rooms. Removed messages show as "Message removed" so replies still make sense.

## Attendance and follow-up

**Members check themselves in** at `/check-in` (or by scanning the QR code on the church screen), from 30 minutes before a service until 30 minutes after it ends. Staff schedule services under **Attendance**, optionally repeating weekly, and choose whether each takes in-person check-in, online check-in, or both.

- **At church**, the member's phone location must be within the radius the church sets around the venue (150 m by default) and accurate enough (200 m). A phone's location can be faked with a browser extension, so members also enter the six-digit code on the **check-in screen**, which changes every minute. Churches that would rather not use the code can switch it off in Settings. Only the distance from the venue is stored, never the member's coordinates.
- **Online**, members enter the code shown on the live stream while check-in is open; codes from the last three minutes are accepted to allow for stream delay. Show the code screen as a browser source or overlay in the streaming software. This counts people who were really watching, rather than counting views.
- Each member checks in once per service. Ushers can still tick people present by hand (no phone, no account), and record each service's headcount.
- Location needs HTTPS: browsers only share location with secure sites.

Set the venue under **Settings & roles › Attendance check-in**. The easiest way is to stand inside the church and press "Use my location".

**Follow-up & care** is a single list for the pastoral team. Follow-ups are raised automatically, and can also be added by hand:

| Who | When | Due |
| --- | --- | --- |
| First-timers (cards from `/welcome` or typed in) | when the card is added | within 2 days |
| Members who joined online, once approved | on approval | within a week |
| Active members who've missed Sundays | after 3 in a row (adjustable) | the next day |

Only Sundays where attendance was actually taken count as missed, members aren't counted as missing Sundays before they joined, and anyone seen at any service since (midweek included) isn't flagged. When someone comes back, their follow-up closes itself. Staff assign follow-ups to each other (the person gets an email), call or WhatsApp from the list, and record how it went. Member profiles show attendance over the last eight Sundays and their follow-up history. `php artisan care:scan` runs every morning from the scheduler.

## Units and their heads of department

Each unit (choir, ushering, media, welfare…) has a **head of department (HOD)** and an assistant, set in admin › Units & fellowships. They sign in with their member account and press "Lead {unit}" on their account page to open the unit's own area at `/units/{id}`:

- **Overview**: members, Sunday attendance rate, the next meeting, the rota, members not seen for three weeks, and birthdays coming up.
- **Members**: add people to the unit (or move them in from another), remove them, call them.
- **Attendance**: each member against the last eight Sundays where attendance was taken and the unit's recent meetings, with an attendance rate.
- **Meetings**: rehearsals, planning or prayer meetings at a place or online (Zoom, Meet, Teams link). Members are emailed, see the meeting on their account page and check in with a six-digit code the HOD shows (it changes every minute), or the HOD ticks them. Minutes are kept with the meeting.
- **Rota**: who serves at each coming service, in what role. Members see their duties on their account page and get an email two days before (`units:remind`).
- **Messages**: emailed to the whole unit and shown on members' account pages.

Staff who manage units (church office, pastors, administrators) can open any unit's area. A unit's type adds tools: **Welfare** or **Media**.

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

- **Requests**: unit heads (and their assistants) and church staff ask the media unit for something from "Ask the media team" on their account page (staff also have it in the admin menu). Types: flyer or poster, social media graphic, video, photo coverage, livestream or recording, slides, or something else. They give the details, the event date and when it's needed (7 days' notice is suggested), and can attach up to 5 files (logos, photos to use, examples). The media HOD and assistant are emailed. On the team's Requests page (To do, Mine, Delivered, Closed, with overdue and due-soon flags), any team member can take an unassigned request; leaders assign, reassign or decline with a reason. Whoever has it delivers files and links with a note. The person who asked is emailed at each step. They can download the work, mark it done or send it back with changes, and cancel while it's open. Both sides can message on the request; team notes stay inside the team.
- **File library**: a private store for photos, videos, audio, designs (PSD, AI, SVG and others), documents and zips, plus links to big files kept on Google Drive, Dropbox or YouTube. Leaders make folders; anyone on the team uploads (up to 20 files at a time, 500 MB each by default, set with `MEDIA_MAX_UPLOAD_MB`), tags and searches. Pictures get small previews. Work delivered for requests appears under "From requests" and can be filed into a folder. Files sit on the private disk (`storage/app/private`), and the app only serves them to the media team, or to whoever asked for a request. Design files, SVGs and other non-media files are only ever downloaded, never opened in the browser. For big uploads the server's own limits must be raised to match: PHP `upload_max_filesize` and `post_max_size`, and the web server's body size (for example nginx `client_max_body_size`). Include `storage/app/private` in backups.

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

Emails (receipts, tickets, reminders, welcome links) use Laravel's mailer: set `MAIL_*` in `.env`. Uploaded images live on the `public` disk, so run `php artisan storage:link` once per server. Expense receipts are private and stay on the `local` disk.

## Going live

What the church or Donaitech needs to do; none of it can be done from the code.

**1. A server.** Any host that runs PHP 8.3+ with a MySQL or PostgreSQL database (a VPS, Laravel Forge, Ploi, or shared hosting with SSH). Point the domain at it and turn on HTTPS (Let's Encrypt is free).

**2. Install.**

```sh
git clone <repo> && cd <repo>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
# edit .env: APP_URL, APP_ENV=production, APP_DEBUG=false, DB_*, MAIL_*, PAYSTACK_*, CHURCH_*, PLATFORM_FEE_*
php artisan migrate --force
php artisan db:seed --force          # starting units, funds and market periods
php artisan storage:link
php artisan admin:create you@church.org --name="Your name"
php artisan admin:create pastor@church.org --name="Pastor's name" --super   # the pastor in charge
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

The web server's document root is `public/`, and `storage/` and `bootstrap/cache/` must be writable.

**3. The scheduler.** Social posts, YouTube syncing, and the weekly email, texts and notifications (sent in batches) run every minute; absence follow-ups, rota reminders and seller renewal reminders run each morning; the weekly email is queued on Monday morning; unused article pictures are cleared out on Sundays. Add one cron entry:

```
* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
```

**4. Email.** Set `MAIL_MAILER`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_FROM_ADDRESS` for a sending service (Mailgun, Postmark, Amazon SES, Zoho or the church's own mailbox). Until then nothing is emailed: receipts, password links and reminders.

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

**8. Text messages and app notifications.**
- App notifications: run `php artisan push:keys` once on the server (it saves the keys in Settings). Installing PHP's `gmp` extension makes sending them much faster. Notifications need HTTPS.
- Text messages: open a [Termii](https://termii.com) account, register a sender ID (the name texts come from, e.g. RoyalTribe; approval takes a few days), fund the balance, and put the API key, sender ID and base URL from the Termii dashboard in Settings & roles › Text messages. The "generic" route doesn't reach numbers on Do-Not-Disturb; the "dnd" route does but costs more and Termii must enable it for the account. Each text is charged per 160 characters (153 for longer messages).

**9. Uploads.** Gallery uploads send several photos at once, so raise PHP's limits to match: `upload_max_filesize = 16M`, `post_max_size = 200M`, `max_file_uploads = 40`, and `memory_limit = 256M` for resizing large photos (plus `client_max_body_size 200m;` on nginx). The upload page tells staff to send fewer photos if a limit is hit.

**10. Search engines.** Submit `https://<site>/sitemap.xml` in Google Search Console.

**11. Backups.** Back up the database daily and `storage/app` (uploaded photos and receipts).

### Deploying for another church

Repeat the steps above on a new server, with the new church's `CHURCH_*` and `BRAND_*` values in `.env` and its logo in `public/images/`. Everything else (address, bank accounts, leaders, links, staff) is set in admin, so the code doesn't change between churches.

## Running it

Needs PHP 8.3+, Composer and Node 20.19+ (or 22.12+).

```sh
composer setup        # installs PHP and npm packages, creates .env, runs migrations, builds assets
php artisan db:seed   # starting content from the design, units, funds and plans
php artisan serve     # site at http://localhost:8000
npm run dev           # in a second terminal while editing CSS/JS, for live reload
```

Tests and code style:

```sh
php artisan test
./vendor/bin/pint
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
- `app/Services/`: Paystack and payment settlement, shop approval and seller reminders, the sermon library, `Messaging/` (weekly email, Termii texts, web push, the broadcaster), `Content/Markdown` (safe article rendering) and `Content/Images` (photo resizing)
- `resources/views/`: `layouts/` (site, admin, seller), `partials/`, `components/`, `pages/`, `account/`, `blog/`, `market/`, `vendor/`, `admin/`, `mail/`
- `resources/css/`: `base.css` holds the design tokens; `partials/` and `pages/` hold the rest; `admin.css` is the dashboard's own stylesheet
- `resources/js/`: mobile menu, carousel, filters, share/copy buttons, countdown, give form, gallery viewer, community chat and quiz, the app shell (install button, notifications); in admin, the article editor and gallery uploads

## Forms

Prayer requests, newsletter sign-ups, contact messages, first-timer cards, market enquiries and applications are all rate limited and include a hidden field to catch simple spam bots. Staff read them in the admin dashboard.

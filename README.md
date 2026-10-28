# RCCG The Royal Tribe Embassy — website

The public website, built with [Laravel](https://laravel.com) 13 from the "RCCG The Royal Tribe Embassy — App & Website" design. Pages are Blade templates styled with plain CSS taken from the design's style guide; Vite bundles the CSS and the small amount of JavaScript.

## Pages

| Path | Page |
| --- | --- |
| `/` | Home: highlights carousel, declaration, announcements, messages, blog, events, Tribe Market, prayer request, give, app |
| `/about` | Story, mission, pastors, beliefs, units, plan your visit and FAQs |
| `/watch` | Live stream player, series and a filterable message library |
| `/blog` | Featured article and articles filterable by topic (`/blog?topic=Faith` preselects a topic) |
| `/events` | Featured event with countdown, monthly calendar, regular gatherings; `/events/{slug}` for RSVPs and tickets |
| `/give` | Online giving to the church's funds, bank transfer details, appeal progress |
| `/market` | The member marketplace for products and services; `/market/open-a-shop` to apply |
| `/notices` | Paid notice board for other churches and businesses; `/notices/advertise` to place one |
| `/join`, `/login`, `/account` | Membership sign-up, sign-in, and the member's own page (details, giving, tokens) |
| `/welcome` | First-timer card for visitors; print it as a QR code at the door |
| `/check-in` | Members check in to a service, at church (location and screen code) or online (stream code) |
| `/contact`, `/privacy`, `/terms` | Contact form and legal pages |
| `/vendor` | Sellers manage listings, enquiries, subscription and shop details (sign in at `/vendor/login`) |

## Accounts and roles

There are three kinds of account, all in the `users` table:

- **Members** join at `/join` and wait for the church office to approve them. They update their details, see their giving and pay tokens at `/account`.
- **Sellers** apply at `/market/open-a-shop`; approving the shop emails them a link to set a password.
- **Staff** have a role from `config/roles.php`, and each role opens only its own admin areas:

| Role | Admin areas |
| --- | --- |
| Administrator | Everything, including Settings & roles |
| Pastor | Everything except Settings & roles, including welfare cases and approvals above the HOD's limit |
| Finance | Giving & tokens, expenses & budgets, the market |
| Church office | Members, attendance & first-timers, follow-up & care, units, subscribers, the market, content, notices, events, prayer, messages |
| Media | Content, notices, sermons & live, events |
| Ushering | Attendance & first-timers, events (door check-in) |

Create the first administrator from the command line; they add everyone else in **Settings & roles**, which emails each new person a link to set their password:

```sh
php artisan admin:create pastor@example.com --name="Pastor Ade"   # asks for a password, or generates one
php artisan admin:revoke pastor@example.com                       # removes staff access
```

Sign-in is limited to five wrong attempts a minute per email. The site uses Lagos time (`APP_TIMEZONE`).

## Admin dashboard

Staff sign in at `/admin`. Every area in the design's sidebar is built:

- **Dashboard**: members, first-timers, giving, tokens and market income this month, the last eight Sundays' attendance, what needs attention, giving by fund, latest gifts, upcoming events, prayer requests and new members. Each person only sees the panels their role covers.
- **Members**: the register by status (members, waiting for approval, inactive), search and filters, profiles with milestones (baptised, believers' class, workers' training) and token plan, approval, and CSV import and export.
- **First-timers & follow-up**: cards from `/welcome` or typed in by staff; mark each person as called with a note, and add them to the register.
- **Attendance**: schedule services with self check-in at church and online, show the code screen, see who checked in, tick people present by hand, and record headcounts.
- **Follow-up & care**: follow-ups for first-timers, new members and members not seen lately, assigned to the care team with outcomes recorded.
- **Units & fellowships**: the service units and house fellowships members choose from.
- **Newsletter subscribers**: search, remove, download as CSV.
- **Giving & tokens**: totals for this month, last month or this year; fund balances and appeal targets; every transaction with CSV export; cash offerings counted at services; token plans; tokens paid at the office; members in arrears with email and WhatsApp reminders.
- **Expenses & budgets**: spending logged with a receipt, approved by someone other than the person who logged it, against monthly budgets per category; CSV export.
- **Tribe Market**: shop applications, shops and subscription status, plan prices, coupons, and subscription payments with CSV export.
- **Content & announcements**, **Notice board**, **Events & tickets**, **Prayer requests**, **Contact messages**.
- **Sermons & live**: the message library and series on the home and Watch pages, and the YouTube stream in the Watch page player.
- **Settings & roles**: staff and their roles, and the church's details (address, service times, bank details, leaders, live stream, social links). Saved values replace the placeholders across the site.

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
- **Social media**: team members draft posts (caption, photo or video, platforms, optional time) and send them to the HOD, who approves, schedules or publishes. **Facebook Page and Instagram** are posted by the app through Meta's Graph API; Instagram videos are published once Instagram has processed them (`social:publish` runs every minute). **YouTube, X, TikTok and WhatsApp** get a share kit: copy the caption, download the file, open the platform. To connect Facebook and Instagram, an administrator adds the Page ID, a Page access token (with `pages_manage_posts` and `instagram_content_publish`) and the Instagram business account ID in Settings & roles; the token is stored encrypted. Meta fetches photos and videos from the site, so posting only works once the site is on a public HTTPS address.

## Money: giving, tokens, tickets, the market and the notice board

All online payments go through Paystack and are recorded in one `payments` table (amounts in kobo). A payment only counts once Paystack confirms the amount and currency, either when the buyer returns or through the signed webhook at `/webhooks/paystack`; the same confirmation arriving twice changes nothing.

**Who gets what.** Each deployment runs on Donaitech's Paystack integration with the church as a subaccount, so:

| Payment | Church | Donaitech |
| --- | --- | --- |
| Gift (tithe, offering, appeals) | the gift | `PLATFORM_FEE_GIVING`% |
| Member tokens | the tokens | `PLATFORM_FEE_TOKENS`% |
| Event ticket | the ticket price | `PLATFORM_FEE_TICKETS`% |
| Notice board listing | the package price | `PLATFORM_FEE_NOTICES`% |
| Seller subscription | the plan price, after any coupon | `PLATFORM_FEE_MARKET`% |

Market sales themselves never go through the site: buyers pay sellers directly.

Paystack divides each payment at the moment it's made, so nothing is paid out by hand. Settings: `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SUBACCOUNT` (the church), the `PLATFORM_FEE_*` percentages, notice prices in `config/notices.php`, and the market's subscription periods and coupon percentages in `config/market.php`. In the Paystack dashboard, point the webhook to `https://<site>/webhooks/paystack`.

**Event tickets** (admin › Events & tickets): ticket types per event, free or paid, with optional limits. Unpaid orders hold their places for 30 minutes. Every attendee gets a QR code; scanning it opens the door check-in page for signed-in staff.

**Market** (admin › Tribe Market): members apply to open a shop, and approving emails them a link to set a password. At `/vendor`, sellers list products and services (priced fixed or "per hour", "per session" and so on), read buyer enquiries, and pay a subscription to be listed: monthly, quarterly, 6 months, 1 year or 3 years. The admin prices each period (a plan is only offered once it's priced and switched on) and creates coupons for 10%, 20%, 50% or 100% off, with optional use limits and end dates. A 100% coupon activates with no payment. Renewing early adds the new period after the current one. A shop's listings show only while it is approved and subscribed. Buyers contact the seller by phone, WhatsApp, email or an enquiry form (with a date for services), and arrange payment with them.

**Notice board** (admin › Notice board): other churches and businesses pay to list a notice; nothing appears until an admin approves it, and it runs for the days paid for. Rejected notices are refunded from the Paystack dashboard.

**Giving** (admin › Giving & tokens): the Give page takes one-off gifts to any active fund (tithe, offering, building fund, missions and welfare to start with, from `config/finance.php`). A fund with a target shows its progress on the Give page. Cash, cheques and transfers are recorded by finance staff. Recurring monthly giving isn't offered yet; it would need Paystack's subscription plans.

**Tokens**: monthly member dues on a plan (standard, student, exempt to start with). Members owe from the month they're approved; payments cover the next unpaid months. Members pay online at `/account/tokens`, or finance records payments made at the office.

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
php artisan db:seed --force          # starting units, funds, token plans and market periods
php artisan storage:link
php artisan admin:create you@church.org --name="Your name"
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

The web server's document root is `public/`, and `storage/` and `bootstrap/cache/` must be writable.

**3. The scheduler.** Social posts go out every minute; absence follow-ups, rota reminders and seller renewal reminders run each morning. Add one cron entry:

```
* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
```

**4. Email.** Set `MAIL_MAILER`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_FROM_ADDRESS` for a sending service (Mailgun, Postmark, Amazon SES, Zoho or the church's own mailbox). Until then nothing is emailed: receipts, password links and reminders.

**5. Paystack.** In the Paystack dashboard: create the church's subaccount and put its code in `PAYSTACK_SUBACCOUNT`; copy the live secret and public keys into `PAYSTACK_SECRET_KEY` and `PAYSTACK_PUBLIC_KEY`; set the webhook URL to `https://<site>/webhooks/paystack`. Until the keys are set, the site shows bank transfer details instead of taking payments.

**6. In admin.** Sign in at `/admin`, then:
- **Settings & roles**: fill in the address, service times, bank details, leaders' names, social links and church email, and add the other staff.
- **Tribe Market**: price the subscription periods and switch them on.
- **Giving & tokens**: check the funds and token plan amounts.
- **Sermons & live**: add the first messages and the current series.
- **Members**: import the existing register from a spreadsheet.

**7. Backups.** Back up the database daily and `storage/app` (uploaded photos and receipts).

### Deploying for another church

Repeat the steps above on a new server, with the new church's `CHURCH_*` and `BRAND_*` values in `.env` and its logo in `public/images/`. Everything else (address, bank details, leaders, links, staff) is set in admin, so the code doesn't change between churches.

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

Most content is edited in admin: announcements, the declaration, events, sermons and series, the live stream, funds, and the church's details in Settings & roles. A few things still live in config files:

- `config/church.php`: name and branding (from `.env`), navigation, the gold banner, and defaults for what Settings & roles can change
- `config/content/posts.php`: blog articles
- `config/content/about.php`: values, beliefs, units text and FAQs
- `config/content/sermons.php`: example messages, shown until the church adds its own
- `config/finance.php`, `config/market.php`, `config/notices.php`, `config/roles.php`: funds and token plans to start with, expense categories, market periods and coupons, notice prices, staff roles

Anything still in `[brackets]` is a placeholder waiting for real details; most disappear once Settings & roles is filled in. Photo areas are coloured placeholders until real photography is added. If you run `php artisan config:cache`, run it again after editing config files (admin settings don't need it).

## Where things are

- `routes/web.php`: all routes; `routes/console.php`: `admin:create`, `admin:revoke`, `care:scan`, `units:remind`, `social:publish`, `market:remind` and the schedule
- `app/Http/Controllers/`: public pages and forms, `Account/` (members), `Vendor/` (sellers), `Admin/` (dashboard)
- `app/Models/`: people (`User`, `Member`, `Unit`, `Fellowship`, `FirstTimer`, `Service`), money (`Payment`, `Fund`, `Gift`, `TokenPlan`, `TokenPayment`, `Expense`, `Budget`), events and notices (`Event`, `TicketType`, `TicketOrder`, `Ticket`, `Notice`), the market (`Shop`, `Product`, `Enquiry`, `VendorPlan`, `VendorSubscription`, `Coupon`), content (`Announcement`, `Declaration`, `Sermon`, `SermonSeries`, `PrayerRequest`, `NewsletterSubscriber`, `ContactMessage`, `Setting`)
- `app/Services/`: Paystack and payment settlement, shop approval and seller reminders, the sermon library
- `resources/views/`: `layouts/` (site, admin, seller), `partials/`, `components/`, `pages/`, `account/`, `blog/`, `market/`, `vendor/`, `admin/`, `mail/`
- `resources/css/`: `base.css` holds the design tokens; `partials/` and `pages/` hold the rest; `admin.css` is the dashboard's own stylesheet
- `resources/js/`: mobile menu, carousel, filters, share/copy buttons, countdown, give form

## Forms

Prayer requests, newsletter sign-ups, contact messages, first-timer cards, market enquiries and applications are all rate limited and include a hidden field to catch simple spam bots. Staff read them in the admin dashboard.

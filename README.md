# RCCG The Royal Tribe Embassy — website

The public website, built with [Laravel](https://laravel.com) 13 from the "RCCG The Royal Tribe Embassy — App & Website" design. Pages are Blade templates styled with plain CSS taken from the design's style guide; Vite bundles the CSS and the small amount of JavaScript.

## Pages

| Path | Page |
| --- | --- |
| `/` | Home: highlights carousel, declaration, announcements, messages, blog, events, Tribe Market, prayer request, give, app |
| `/about` | Story, mission, pastors, beliefs, units, plan your visit and FAQs |
| `/watch` | Live service, series and a filterable message library |
| `/blog` | Featured article and articles filterable by topic (`/blog?topic=Faith` preselects a topic) |
| `/blog/{slug}` | One article |
| `/events` | Featured event with countdown, monthly calendar, regular gatherings |
| `/give` | Giving form, bank transfer and USSD details, building fund |
| `/events/{slug}` | One event: details, free RSVP or paid tickets, add to calendar |
| `/market` | The member marketplace for products and services; shop and listing pages, and `/market/open-a-shop` to apply |
| `/notices` | Paid notice board for other churches and businesses; `/notices/advertise` to place one |
| `/vendor` | Sellers manage their products and orders (sign in at `/vendor/login`) |

## Admin dashboard

Church staff sign in at `/admin`. There's no public sign-up; create accounts from the command line:

```sh
php artisan admin:create pastor@example.com --name="Pastor Ade"   # asks for a password, or generates one
php artisan admin:revoke pastor@example.com                       # removes admin access
```

What's built so far, following the admin screens in the design:

- **Dashboard**: unread prayer requests, newsletter sign-ups, live and scheduled announcements, and reminders to set the weekly declaration.
- **Prayer requests**: the inbox for the home page form. Mark requests as prayed for, filter to unread or private, delete.
- **Newsletter subscribers**: search, remove, and download the list as CSV for an email service.
- **Content & announcements**: write announcements for the home page (publish now, schedule, or save as a draft) and set the weekly declaration for any week. The home page shows the newest three live announcements and the current week's declaration.

The sidebar lists the rest of the design (members, giving and tokens, Tribe Market, sermons, events, settings) marked "Soon". Those need member accounts and payments, which don't exist yet.

Sign-in is limited to five wrong attempts a minute per email. The site uses Lagos time (`APP_TIMEZONE`), so schedules and "this week" match the church.

## Money: tickets, the market and the notice board

All online payments go through Paystack and are recorded in one `payments` table (amounts in kobo). A payment only counts once Paystack confirms the amount and currency, either when the buyer returns or through the signed webhook at `/webhooks/paystack`; the same confirmation arriving twice changes nothing.

**Who gets what.** Each deployment runs on Donaitech's Paystack integration with the church as a subaccount, so:

| Sale | Vendor | Church | Donaitech |
| --- | --- | --- | --- |
| Event ticket | | the ticket price | `PLATFORM_FEE_TICKETS`% |
| Notice board listing | | the package price | `PLATFORM_FEE_NOTICES`% |
| Market order | the rest, less Paystack's fee | the market commission (admin setting) | `PLATFORM_FEE_MARKET`% |

Paystack divides each payment at the moment it's made, so nothing is paid out by hand. Settings: `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SUBACCOUNT` (the church), the `PLATFORM_FEE_*` percentages, notice prices in `config/notices.php`, and the starting market commission in `config/market.php`. In the Paystack dashboard, point the webhook to `https://<site>/webhooks/paystack`.

**Event tickets** (admin › Events & tickets): ticket types per event, free or paid, with optional limits. Unpaid orders hold their places for 30 minutes. Every attendee gets a QR code; scanning it opens the door check-in page for signed-in staff.

**Market** (admin › Tribe Market): members apply to open a shop; approving creates their Paystack payout account and emails them a link to set a password. At `/vendor`, sellers list products (with optional stock) and services (booked for a date, priced fixed or "per hour", "per session" and so on), and move orders along: ready then delivered for products, confirmed then done for services. Orders are one listing at a time (no basket yet).

**Notice board** (admin › Notice board): other churches and businesses pay to list a notice; nothing appears until an admin approves it, and it runs for the days paid for. Rejected notices are refunded from the Paystack dashboard.

Emails (tickets, order receipts, seller welcome) use Laravel's mailer: set `MAIL_*` in `.env`. Uploaded images live on the `public` disk, so run `php artisan storage:link` once per server.

## Deploying for another church

1. Copy the app and set the `CHURCH_*` and `BRAND_*` values in `.env` (name, wordmark, logo path, market name, colours).
2. Replace the logo in `public/images/` and edit `config/church.php` (address, service times, links, bank details) and `config/content/` (sermons, blog, about page).
3. Create the church's Paystack subaccount and set `PAYSTACK_SUBACCOUNT`, plus Donaitech's `PLATFORM_FEE_*`.
4. `php artisan migrate`, `php artisan storage:link`, then `php artisan admin:create` for the church's first admin.

## Running it

Needs PHP 8.3+, Composer and Node 20.19+ (or 22.12+).

```sh
composer setup        # installs PHP and npm packages, creates .env, runs migrations, builds assets
php artisan db:seed   # starting announcements and this week's declaration from the design
php artisan serve     # site at http://localhost:8000
npm run dev           # in a second terminal while editing CSS/JS, for live reload
```

Tests and code style:

```sh
php artisan test
./vendor/bin/pint
```

## Editing content

Church details and page content live in config files, not in the templates:

- `config/church.php`: name, address, service times, bank details, social and app links, navigation, the gold banner
- `config/content/sermons.php`: messages, series, upcoming live services
- `config/content/posts.php`: blog articles (each gets its own page)
- `config/content/home.php`: Tribe Market picks, testimony
- `config/content/about.php`: values, pastors, beliefs, units, FAQs

Anything in `[brackets]` is a placeholder from the design waiting for real details. Links set to `#` are waiting for real URLs. Photo areas are coloured placeholders until real photography is added. If you run `php artisan config:cache` in production, run it again after editing these files.

Announcements, the weekly declaration, events, market shops and notices are managed in the admin dashboard instead.

## Where things are

- `routes/web.php`: all routes
- `app/Http/Controllers/`: `PageController` (home, about, watch, events, give), `BlogController`, the two form handlers, and `Admin/` for the dashboard
- `app/Models/`: content (`Announcement`, `Declaration`, `PrayerRequest`, `NewsletterSubscriber`), money (`Payment`, `Notice`, `Event`, `TicketType`, `TicketOrder`, `Ticket`, `Shop`, `Product`, `MarketOrder`), `Setting`, `User`
- `app/Services/Payments/`: the Paystack client and payment settlement; `app/Services/Market/`: shop approval
- `resources/views/`: `layouts/app.blade.php` and `layouts/admin.blade.php`, `partials/` (header, footer), `components/` (icon, crown, post card), `pages/`, `blog/`, `admin/`
- `resources/css/`: `base.css` holds the design tokens and shared styles; `partials/` and `pages/` hold the rest; `admin.css` is the dashboard's own stylesheet
- `resources/js/`: mobile menu, carousel, filters, share/copy buttons, countdown, give form

## Forms

- **Prayer requests** (home page) are saved to the `prayer_requests` table, including whether the person asked to keep it private.
- **Newsletter sign-ups** (footer) are saved to the `newsletter_subscribers` table. Each email is stored once.

Both forms are rate limited and include a hidden field to catch simple spam bots. Staff read them in the admin dashboard.

## Not connected yet

**Online giving** (tithes and offerings on `/give`) still isn't wired to Paystack, though the payments code it needs now exists. Until it is, the Give button points people to the bank transfer details.

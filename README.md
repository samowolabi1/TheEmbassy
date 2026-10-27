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
- `config/content/events.php`: the featured event and the calendar
- `config/content/home.php`: Tribe Market picks, testimony
- `config/content/about.php`: values, pastors, beliefs, units, FAQs

Anything in `[brackets]` is a placeholder from the design waiting for real details. Links set to `#` are waiting for real URLs. Photo areas are coloured placeholders until real photography is added. If you run `php artisan config:cache` in production, run it again after editing these files.

Announcements and the weekly declaration are edited in the admin dashboard instead. The rest can move there as the matching admin sections are built.

## Where things are

- `routes/web.php`: all routes
- `app/Http/Controllers/`: `PageController` (home, about, watch, events, give), `BlogController`, the two form handlers, and `Admin/` for the dashboard
- `app/Models/`: `PrayerRequest`, `NewsletterSubscriber`, `Announcement`, `Declaration`, `User` (`is_admin`)
- `resources/views/`: `layouts/app.blade.php` and `layouts/admin.blade.php`, `partials/` (header, footer), `components/` (icon, crown, post card), `pages/`, `blog/`, `admin/`
- `resources/css/`: `base.css` holds the design tokens and shared styles; `partials/` and `pages/` hold the rest; `admin.css` is the dashboard's own stylesheet
- `resources/js/`: mobile menu, carousel, filters, share/copy buttons, countdown, give form

## Forms

- **Prayer requests** (home page) are saved to the `prayer_requests` table, including whether the person asked to keep it private.
- **Newsletter sign-ups** (footer) are saved to the `newsletter_subscribers` table. Each email is stored once.

Both forms are rate limited and include a hidden field to catch simple spam bots. Staff read them in the admin dashboard.

## Not connected yet

**Online giving** needs a Paystack account. Set `PAYSTACK_PUBLIC_KEY` in `.env`, then open Paystack checkout where the `TODO` sits in `resources/js/give.js`; payments should also be confirmed server-side with Paystack's verify API before being recorded. Until then the Give button tells people online giving isn't switched on and points them to the bank transfer details.

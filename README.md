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

## Running it

Needs PHP 8.3+, Composer and Node 20.19+ (or 22.12+).

```sh
composer setup        # installs PHP and npm packages, creates .env, runs migrations, builds assets
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
- `config/content/home.php`: weekly declaration, announcements, Tribe Market picks, testimony
- `config/content/about.php`: values, pastors, beliefs, units, FAQs

Anything in `[brackets]` is a placeholder from the design waiting for real details. Links set to `#` are waiting for real URLs. Photo areas are coloured placeholders until real photography is added. If you run `php artisan config:cache` in production, run it again after editing these files.

When the admin dashboard is built, this content can move into the database so staff can edit it without touching code.

## Where things are

- `routes/web.php`: all routes
- `app/Http/Controllers/`: `PageController` (home, about, watch, events, give), `BlogController`, and the two form handlers
- `resources/views/`: `layouts/app.blade.php`, `partials/` (header, footer), `components/` (icon, crown, post card), `pages/`, `blog/`
- `resources/css/`: `base.css` holds the design tokens and shared styles; `partials/` and `pages/` hold the rest
- `resources/js/`: mobile menu, carousel, filters, share/copy buttons, countdown, give form

## Forms

- **Prayer requests** (home page) are saved to the `prayer_requests` table, including whether the person asked to keep it private.
- **Newsletter sign-ups** (footer) are saved to the `newsletter_subscribers` table. Each email is stored once.

Both forms are rate limited and include a hidden field to catch simple spam bots. There's no screen to read them yet. Until the admin dashboard exists, use `php artisan tinker`, e.g. `App\Models\PrayerRequest::latest()->get()`.

## Not connected yet

**Online giving** needs a Paystack account. Set `PAYSTACK_PUBLIC_KEY` in `.env`, then open Paystack checkout where the `TODO` sits in `resources/js/give.js`; payments should also be confirmed server-side with Paystack's verify API before being recorded. Until then the Give button tells people online giving isn't switched on and points them to the bank transfer details.

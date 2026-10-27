# RCCG The Royal Tribe Embassy — website

The public website, built with [Astro](https://astro.build) from the "RCCG The Royal Tribe Embassy — App & Website" design. It builds to plain static HTML, so it can be hosted on Netlify, Vercel, Cloudflare Pages or any static host.

## Pages

| Path | Page |
| --- | --- |
| `/` | Home: highlights carousel, declaration, announcements, messages, blog, events, Tribe Market, prayer request, give, app |
| `/about` | Story, mission, pastors, beliefs, units, plan your visit and FAQs |
| `/watch` | Live service, series and a filterable message library |
| `/blog` | Featured article and articles filterable by topic (`/blog?topic=Faith` preselects a topic) |
| `/blog/<slug>` | One article |
| `/events` | Featured event with countdown, monthly calendar, regular gatherings |
| `/give` | Giving form, bank transfer and USSD details, building fund |

## Running it

Needs Node 22.23.4 or newer (see `.nvmrc`).

```sh
npm install
npm run dev      # local site at http://localhost:4321
npm run build    # static site in dist/
npm run check    # type-check
```

## Editing content

Content lives in `src/data/`, not in the page markup:

- `site.ts`: church name, address, service times, bank details, social and app links, navigation, the gold banner
- `sermons.ts`: messages, series, upcoming live services
- `posts.ts`: blog articles (each gets its own page)
- `events.ts`: the featured event and the calendar
- `home.ts`: weekly declaration, announcements, Tribe Market picks, testimony

Anything in `[brackets]` is a placeholder from the design waiting for real details. Links set to `#` are waiting for real URLs. Photo areas are coloured placeholders until real photography is added.

Colours, type and shared styles are in `src/styles/global.css`, taken from the design's style guide.

## Not connected yet

These need a backend or account before they do anything real. Until then they tell visitors plainly that they're not switched on, rather than pretending to work:

- **Prayer requests** post JSON to `PUBLIC_PRAYER_ENDPOINT`.
- **Newsletter sign-up** posts JSON to `PUBLIC_NEWSLETTER_ENDPOINT`.
- **Online giving** needs a Paystack account: set `PUBLIC_PAYSTACK_KEY`, then open Paystack checkout where the `TODO` sits in `src/pages/give.astro`.

Copy `.env.example` to `.env` to set these.

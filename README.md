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
| Church office | Members, attendance & first-timers, follow-up & care, units, the weekly email, the market, content (announcements, blog, pages), gallery, community moderation, notices, events, prayer, messages |
| Media | Content (announcements, blog, pages), gallery, community moderation, notices, sermons & live, events, and the media unit's tools (live studio, requests, file library, birthday cards) |
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
- **Record income & spending** (every staff member): write down money that came in (offerings counted after a service, thanksgiving, pledges, donations, event income, sales, hall and equipment hire, welfare contributions and more) or went out, with the date, how it came in (cash, transfer, POS), notes and a receipt or count sheet. Staff see what they've recorded and whether it's been confirmed, approved or declined (with the reason).
- **Income & summary** (finance and pastors, the "Income, expenses & budgets" area): confirm or decline recorded income (nobody confirms their own, except an administrator). Each month shows money in, money out and what's left over, both broken down by category, and exports to one CSV. The income and expense categories are kept here: add new ones, rename them (past entries and budgets follow) or hide ones no longer used. Tithes and online giving stay under Giving, which only the pastor in charge sees.
- **Expenses & budgets**: spending logged with a receipt, approved by someone other than the person who logged it, against monthly budgets per category; CSV export.
- **Library**: the lending desk (hand books over, take them back, chase overdue ones, the waiting list) and the book catalogue. See [The church library](#the-church-library-and-book-reviews).
- **Tribe Market**: shop applications, shops and subscription status, plan prices, coupons, and subscription payments with CSV export.
- **Content & announcements**, **Notice board**, **Events & tickets**, **Prayer requests**, **Contact messages**.
- **Sending announcements**: "Send" on a live announcement texts it to active members (through Termii; members can turn texts off in their account), notifies every phone that turned on app notifications, or opens WhatsApp with the message ready to post in the church groups or channel. The page shows the SMS length and pages, how many people each channel reaches, and progress. Sending the same announcement twice in a day needs an extra tick.
- **Blog**: write articles in Markdown with a formatting toolbar (headings, bold, links, lists, scripture cards, the "reflect" box) and pictures uploaded straight into the text; a cover photo with a description for screen readers; draft, publish now or schedule for later; preview before publishing; feature one article at the top of the blog. Changing an article's address keeps the old one working (it redirects). Raw HTML and unsafe links are removed when an article is saved.
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
- **Optional services:** Paystack, SMS (Termii), push notifications, YouTube and Facebook/Instagram are off until their keys are set (in `.env`, or later in admin › Settings & roles). Everything else works without them.

**Starting over:** `php artisan migrate:fresh --seed`, then `admin:create` again. **Updating** after pulling new code: `composer install && npm install && npm run build && php artisan migrate`.

**If something's wrong:**
- **Photos don't show:** run `php artisan storage:link`.
- **Unstyled page, or "Vite manifest not found":** run `npm run build`.
- **`could not find driver`:** the PHP `pdo_sqlite` extension is missing.
- **Birthday cards or share pictures fail:** PHP's `gd` needs FreeType and WebP.
- **Port 8000 in use:** `php artisan serve --port=8080`, and set `APP_URL` in `.env` to match.

### Tests and code style

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

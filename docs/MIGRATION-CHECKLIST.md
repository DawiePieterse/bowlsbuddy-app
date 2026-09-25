# Migration checklist

Every step to move Bowls Buddy from the old Zend Framework 2 app (`bowlsbuddy`, kept as the reference) to this
Laravel 12 + Filament rebuild, live on Afrihost, starting with LCE. It pulls together [PLAN.md](PLAN.md) and
[DEPLOY-AFRIHOST.md](DEPLOY-AFRIHOST.md); the details live there, this is the order to tick them off in.

The old app never went live, so there is **no data to carry over** and no old site to switch off: the
migration is rebuilding the behaviour, checking it against the reference, and launching the new app.

Ticked items were done by 25 Sep 2026.

## 1. Foundation

- [x] New private repository `bowlsbuddy-app` with the plan in `docs/PLAN.md`.
- [x] Migrations for the `bs_*` tables (same names, keys and columns), with `utf8mb4`, the composite indexes,
  `CHECK` constraints on `status`, and Laravel's tables prefixed `bb_`.
- [x] Models (`User`, `Rink`, `Booking`, `Reservation`, `Event`, `Option`), the `HasMeta` trait and
  relationships.
- [x] Seeders: LCE (Greens A and B, 6 rinks each, 12:00–17:00, 60-minute slots, 2 players) and demo members.
- [x] Login on `bs_users` (email + `pw`, status checks, rate limited).
- [x] Filament panel with privileges, local initials avatars and the Maintenance page.
- [x] CI: Pint, Larastan, Pest against MySQL, `composer audit`.
- [x] Afrihost build script (`scripts/build-afrihost.sh`, one zip, optional `install.sql`).

## 2. Booking rules (tests first)

- [ ] Run the old app locally with seeded rinks, events, closed greens and bookings; record its greens
  overview numbers and which bookings it accepts or refuses (PLAN 5.3).
- [ ] `player-names` stored as JSON (not PHP-serialized) in `bs_bookings_meta`.
- [ ] `BookingRules` with Pest tests for each rule, using the recorded values:
  - [ ] Opening hours, slot length, no past slots, `range_book` / `min_range_book`
  - [ ] Capacity (2 players, one booking per slot)
  - [ ] One rink per member per day (staff with `calendar.create-single-bookings` exempt)
  - [ ] Closed greens block all their rinks for the day
  - [ ] Events block a rink, a green or all rinks
  - [ ] Hidden days (`service.calendar.day-exceptions`)
  - [ ] Cancel cut-off (`range_cancel`)
- [ ] Booking created in a transaction that locks the rink's reservations for the date (`SELECT … FOR
  UPDATE`), with a concurrent-booking test.
- [ ] `GreenService` and the greens overview query (next 14 playing days, free / total slots, events), with
  tests.
- [ ] Day sheet query (rinks by hour, player names, events, closed greens).
- [ ] Caches: options, and the greens overview per day, cleared when a booking, event or closure changes.

## 3. Member pages

- [ ] Layout and CSS carried over from the old app (`public/css`, `public/css-client/default.css`).
- [ ] Registration: first name, surname, email, password, terms and privacy acceptance, anti-bot delay.
- [ ] Log out; "forgot password" page pointing to the Secretary.
- [ ] Greens overview (closed in red, events in purple).
- [ ] Green calendar (player names for members, own bookings in green).
- [ ] Booking pop-up (1–2 players, partner's name, rules acceptance, one-rink-per-day message).
- [ ] WhatsApp share after booking and from the pop-up.
- [ ] Cancel own booking before the cut-off.
- [ ] My bookings.
- [ ] My account: change email or password, download my data, delete account (POPIA).
- [ ] Info page, help page, Business Terms and Privacy Policy PDFs (served through a route, no symlink).
- [ ] Playwright tests for the booking flow at 390 px and 1200 px.

## 4. Secretary, admin and setup

- [ ] Profile page: the Secretary changes their own email and password (needed before launch, see 6).
- [ ] Panel entry limited to `admin.see-menu`, and a policy per resource.
- [ ] Members: search, create, edit, activate, set a temporary password, privileges.
- [ ] Bookings: list, create for a member, edit, cancel, delete.
- [ ] Events: rink / Green A / Green B / all rinks; list, edit, delete.
- [ ] Rinks.
- [ ] Settings: names and text, info and help pages (HTML purified on save), behaviour, terms and privacy
  uploads.
- [ ] Greens page: open or close a green per day, WhatsApp invite, printable day sheet with QR code.
- [ ] Download backup button (SQL dump) in the admin panel.
- [ ] `php artisan club:create` and the first-run setup page (shown only while there are no users).
- [ ] Browser test of the Secretary's day: close a green, add an event, print the day sheet, reset a password.

## 5. Security and performance check

- [ ] Every form has CSRF; nothing changes data through a GET link.
- [ ] Bcrypt cost 12; new session ID at login; secure, HttpOnly, SameSite=Lax cookies.
- [ ] Security headers: `Content-Security-Policy`, `X-Frame-Options: DENY`, `Referrer-Policy`,
  `Permissions-Policy`.
- [ ] `APP_DEBUG=false`, errors logged, friendly error page.
- [ ] `composer audit` clean; Dependabot on.
- [ ] Only first name, surname and email stored (POPIA).
- [ ] Pages under 150 ms server time and 15 queries on Afrihost.
- [ ] Over 80% test coverage of `app/Services`; CI green.

## 6. Hosting (Afrihost)

- [x] Afrihost Bronze Pro package and `bowlsbuddy.co.za` domain.
- [x] Subdomain `lce.bowlsbuddy.co.za`, document root `bowlsbuddy-lce/public`, PHP 8.3.
- [x] Database `bowlsbg5n9w0_lce` with `install.sql` imported.
- [x] Build uploaded and extracted; `.env` created from `.env.afrihost.example`.
- [x] HTTPS certificate (AutoSSL) and Force HTTPS Redirect.
- [x] First Secretary login at `/admin`.
- [ ] Change the Secretary's email and password (the seeded password was shared in a chat, so treat it as
  exposed). Needs the profile page from 4.
- [ ] Ask Afrihost: SSH shell access (and port)? `intl` and `zip` on ea-php83? Daily backups included?
- [ ] Decide what `bowlsbuddy.co.za` shows (landing page or redirect).
- [ ] Deploy the finished build (update steps in DEPLOY-AFRIHOST.md) and run database updates from
  **Maintenance**.

## 7. Launch at LCE

- [ ] Set LCE's names, texts, info and help pages in Settings; upload Business Terms and Privacy Policy.
- [ ] Remove or disable any test and demo accounts on the live database.
- [ ] Take a first backup, and test restoring it on a local copy.
- [ ] Trial with the Secretary and a few members for 1–2 weeks; fix what they find.
- [ ] Invite all members with the WhatsApp invite button.
- [ ] Weekly backups (cPanel Backup, or the admin panel's button) written into someone's routine.

## 8. Wrap up

- [ ] Archive the old `bowlsbuddy` repository on GitHub (read-only reference).
- [ ] Update PLAN.md: phases ticked, status set to live.
- [ ] Next clubs: one subdomain, folder and database each, following "New club" in DEPLOY-AFRIHOST.md.

# Bowls Buddy technology

What Bowls Buddy is built with, where it runs and how it is checked. For the plan see `PLAN.md`, for modules
and licences `MODULES.md`, and for deploying `DEPLOY-AFRIHOST.md`.

---

## 1. At a glance

| Layer | Technology |
|---|---|
| Language | PHP 8.3 in production (8.2 or newer supported) |
| Framework | Laravel 12 |
| Admin panel | Filament 4 (on Livewire 3 and Alpine.js) |
| Member pages | Blade templates with a small hand-written stylesheet; no JavaScript build step |
| Database | MariaDB 10.11 in production; MySQL 8 in CI; MariaDB for local and cloud development |
| Hosting | Afrihost Bronze Pro shared cPanel hosting, LiteSpeed web server |
| Domain | `bowlsbuddy.co.za`, one subdomain per club (for example `lce.bowlsbuddy.co.za`) |
| Code | GitHub (`DawiePieterse/bowlsbuddy-app`), GitHub Actions for CI |
| Tests | Pest (unit and feature), Playwright (browser flows at phone and desktop widths) |
| Quality | Laravel Pint (code style), Larastan / PHPStan level 6 (static analysis), `composer audit` |

Why this stack: the original Bowls Buddy was a Zend Framework 2 app, abandoned since 2019. Laravel is the same
language, so its booking rules could be carried over line by line, and it runs on cheap shared PHP hosting
with no Node.js, Docker or background workers. Filament generates the Secretary's admin screens from the
models (`PLAN.md` section 4).

---

## 2. Application

### Framework and libraries

| Package | Version | Used for |
|---|---|---|
| `laravel/framework` | 12 | Routing, Eloquent, validation, CSRF, auth, rate limiting, migrations, scheduler |
| `filament/filament` | 4 | The Secretary's admin panel at `/admin`: members, bookings, events, rinks, greens, utilisation, settings, licence, maintenance |
| `livewire/livewire` | 3 | Interactive Filament pages (comes with Filament) |
| `chillerlan/php-qrcode` | 5 | The QR code on the printable day sheet |
| `symfony/html-sanitizer` | 7 | Cleans the Info and Help pages the Secretary edits |
| PHP `sodium` (built in) | | Ed25519 signatures on licences (`app/Support/Licensing`) |

### Structure

| Where | What |
|---|---|
| `app/Models` | `User`, `Rink` (`bs_squares`), `Booking`, `Reservation`, `Event`, `Option`; key/value meta tables via `Concerns/HasMeta` |
| `app/Services` | Business logic: booking rules, booking, greens and closed days, direction of play, day sheet, utilisation, club setup, backups |
| `app/Support` | `Settings` (cached site settings), `Phone` (SA cellphone numbers), `Licensing` (modules and licences) |
| `app/Filament` | Admin panel pages and resources |
| `app/Http/Controllers` | Member pages, login and registration, the Secretary's green tools |
| `resources/views` | Blade views for member pages and Filament pages |
| `config/club.php`, `config/modules.php` | Starting setup for a new club; the module registry |

### Conventions that shape the system

- **Times** are local wall-clock times in `Africa/Johannesburg` (UTC+2 all year, no daylight saving).
- **Members log in** with a South African cellphone number (stored as `+27...`) or an email address.
- **Settings** live as rows in `bs_options` and are always read and written through `App\Support\Settings`.
- **No email is sent.** The club runs on WhatsApp: bookings and invitations are shared with WhatsApp links.
  Mail is set to `log`.
- **Security:** CSRF on every form, deletes by POST only, bcrypt cost 12, rate-limited login, secure and
  encrypted session cookies, security headers on every response, HTML from the Secretary sanitised.
- **Privacy (POPIA):** only logged-in members see names; members can download or delete their own details.

---

## 3. Databases

One database per club, all with the same structure, created by Laravel migrations.

| Tables | What they hold |
|---|---|
| `bs_users`, `bs_users_meta` | Members and staff; privileges and details as meta |
| `bs_squares`, `bs_squares_meta` | Rinks ("squares" in the original); a rink's green is the prefix of its name (`A-1` is on green A) |
| `bs_bookings`, `bs_bookings_meta` | Bookings, with players and notes as meta |
| `bs_reservations`, `bs_reservations_meta` | The date and times of each booking |
| `bs_events`, `bs_events_meta` | Blocked time: an event on one rink, a green or all rinks |
| `bs_options` | Site settings, closed days, direction of play, the licence |
| `bb_sessions`, `bb_cache`, `bb_cache_locks`, `bb_migrations` | Laravel's own tables (sessions and cache live in the database on shared hosting) |

- The `bs_*` tables keep the original app's names, keys (`uid`, `sid`, `bid`, `rid`, `eid`) and columns, so
  the original code stays a valid reference.
- Character set `utf8mb4` everywhere (names with any character, emoji included).
- Double bookings are prevented inside a database transaction with row locks, and tested with parallel
  requests (`tests/Concurrency`).

| Environment | Database |
|---|---|
| Production (Afrihost) | MariaDB 10.11, `localhost`, one database per club (`bowlsbg5n9w0_<club>`) |
| CI (GitHub Actions) | MySQL 8.0 service container |
| Development and tests | MariaDB: `bowlsbuddy` (dev), `bowlsbuddy_test` (Pest), `bowlsbuddy_e2e` (Playwright) |

---

## 4. Hosting: Afrihost

| What | Value |
|---|---|
| Package | Afrihost Bronze Pro Linux Hosting, R135 a month |
| Control panel | cPanel (Jupiter theme), reached from Afrihost ClientZone |
| Web server | LiteSpeed; Laravel's `public/.htaccess` works as is |
| PHP | 8.3 (`ea-php83`) per domain in MultiPHP Manager, with `intl` and `zip` |
| Database | MariaDB 10.11, up to 20 databases, so room for about 20 clubs |
| Limits | 7.81 GB disk, 100 subdomains |
| HTTPS | Free AutoSSL certificates per subdomain, renewed automatically; Force HTTPS Redirect on |
| Shell | Jailed SSH (password only, South African IPs) and cPanel Terminal |
| Backups | Afrihost's Afrires keeps 14 days of files and databases (restore by support ticket); the Secretary can also download an SQL backup from the admin panel |

**Layout per club:** a subdomain `<club>.bowlsbuddy.co.za` whose document root is `bowlsbuddy-<club>/public`, so
only `public/` can be reached from the web; the code and `.env` sit next to it. `bowlsbuddy.co.za` itself shows
a static landing page (`landing/index.html`).

**Production settings:** sessions and cache in the database, queue `sync` (no background workers), logs to a
single file at level `error`, debug off.

**Deploying:** `scripts/build-afrihost.sh` builds a ready-to-upload zip with `vendor/` included (no Composer
needed on the server). Upload and extract it per club in the cPanel File Manager, then run database updates
from the admin panel's Maintenance page or with `php artisan migrate` over SSH. A first install adds
`install.sql` for phpMyAdmin. Full steps: `DEPLOY-AFRIHOST.md`.

**Before Afrihost:** the app was first tested on InfinityFree's free hosting (`DEPLOY.md`), which is why every
task can be done without a command line.

---

## 5. Modules and licences

- Every club runs the same code. A licence key, signed with Bowls Buddy's Ed25519 private key, says which
  modules and how many greens the club has paid for, and until when.
- The key is stored in `bs_options` and checked offline against the public keys in `config/modules.php`, so
  there is no licence server to depend on.
- Bookings always work, with or without a licence. Details in `MODULES.md`.
- Invoicing is done in Zoho Invoice (free version); licences are issued with `php artisan licence:issue`.

---

## 6. Development and checks

| Tool | What it checks | Command |
|---|---|---|
| Laravel Pint | Code style | `vendor/bin/pint --test` |
| Larastan (PHPStan 2) | Static analysis, level 6 | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` |
| Pest 3 | Unit, feature and concurrency tests against a real MariaDB or MySQL | `vendor/bin/pest` |
| Playwright | Booking and Secretary flows in Chromium at 390 px (phone) and 1200 px (desktop) | `scripts/e2e.sh` |
| `composer audit` | Known security advisories in dependencies | `composer audit` |

`composer check` runs the first three. **GitHub Actions** (`.github/workflows/ci.yml`) runs Pint, Larastan,
Pest on MySQL 8 and `composer audit` on every push and pull request. Changes reach `main` through a pull
request once CI is green.

**Development environment:** Claude Code on the web, in a cloud container. `.claude/hooks/session-start.sh`
installs MariaDB, creates the three databases, installs the Composer and npm packages, and prepares `.env`
on every session start. Chromium for Playwright is preinstalled.

---

## 7. Costs

| Item | Cost |
|---|---|
| Afrihost Bronze Pro hosting, all clubs | R135 a month |
| `bowlsbuddy.co.za` domain | Included with the hosting package |
| Laravel, Filament, Pest, Playwright and all other libraries | Free, open source |
| GitHub and GitHub Actions | Free tier |
| Zoho Invoice | Free version |
| HTTPS certificates | Free (AutoSSL) |

# Bowls Buddy

Online rink bookings for bowls clubs: members book practice rinks on their phones, see who is playing and
share bookings on WhatsApp; the Club Secretary opens and closes greens, adds events and prints day sheets.

This is the rebuild of the original ZF2-based Bowls Buddy on **Laravel 12 + Filament 4**, keeping the same
`bs_*` data structure. See [docs/PLAN.md](docs/PLAN.md) for the plan and progress, and
[docs/DEPLOY-AFRIHOST.md](docs/DEPLOY-AFRIHOST.md) for deploying to Afrihost ([docs/DEPLOY.md](docs/DEPLOY.md)
for InfinityFree).

## Requirements

- PHP 8.2+ with `intl`, `mbstring`, `pdo_mysql`, `zip`
- MySQL 8 or MariaDB 10.6+
- Composer

## Getting started

```bash
cp .env.example .env            # set DB_* for your local database
composer install
php artisan key:generate
php artisan migrate --seed      # LCE setup (Greens A and B, 12 rinks) + demo members locally
php artisan serve
```

The seeder prints the Club Secretary's password (`secretary@example.com`) unless `CLUB_ADMIN_PASSWORD` is
set. Demo members (local only) use the password `secret123`.

- Member site: <http://127.0.0.1:8000>
- Admin panel: <http://127.0.0.1:8000/admin>

## Checks

Tests run against MySQL/MariaDB (database `bowlsbuddy_test`, user `bb` / `bb` by default; see `phpunit.xml`).

```bash
composer check      # Pint (style), Larastan (static analysis), Pest (tests)
```

GitHub Actions runs the same checks, plus `composer audit`, on every push.

Browser tests (Playwright, phone and desktop widths) run with `scripts/e2e.sh`.

For Claude Code sessions, `CLAUDE.md` holds the working notes and `.claude/hooks/session-start.sh` sets up
MariaDB, the dependencies and `.env` automatically in Claude Code on the web.

## Structure

| Where | What |
|---|---|
| `database/migrations` | The `bs_*` booking tables (same names and keys as the original) and Laravel's `bb_*` tables |
| `app/Models` | `User`, `Rink` (`bs_squares`), `Booking`, `Reservation`, `Event`, `Option`; meta via `Concerns/HasMeta` |
| `app/Support/Settings.php` | Cached site settings from `bs_options` |
| `app/Filament` | Admin panel (Secretary), including the Maintenance page |
| `config/club.php` | Starting setup for a new club |
| `config/modules.php`, `app/Support/Licensing` | Modules and signed licences (see Modules and licences below) |
| `scripts/build-infinityfree.sh` | Builds the zip files to upload to InfinityFree |
| `scripts/build-afrihost.sh` | Builds the zip file to upload to Afrihost (one folder per club) |

## Modules and licences

Every club runs the same code; a licence signed by Bowls Buddy switches modules on per install and sets the
greens paid for. Without one, bookings work as normal. See `docs/MODULES.md`.

```bash
php artisan licence:keygen 2026-1          # once, on your machine: a signing key pair
php artisan licence:issue --club=lce.bowlsbuddy.co.za --modules=rollups --greens=2 --expires=2027-09-30
php artisan licence:install <key>          # on the club's install, or paste it on the Licence page
php artisan licence:show
```

## Licence

Based on [ep-3 Bookingsystem](https://github.com/tkrebs/ep3-bs) (MIT, © Tobias Krebs).

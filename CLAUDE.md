# Bowls Buddy: working notes for Claude

Online rink bookings for bowls clubs. Rebuild of the original ZF2 app on **Laravel 12 + Filament 4**, keeping
the original `bs_*` tables. Read `README.md` for the overview and `docs/PLAN.md` for the plan, the feature
checklist (section 7) and the decisions (section 12). Tick new features off in `docs/PLAN.md` section 7.

## Stack and layout

| Where | What |
|---|---|
| `app/Models` | `User`, `Rink` (`bs_squares`, key `sid`), `Booking` (`bid`), `Reservation` (`rid`, date and times of a booking), `Event` (`eid`, blocked time), `Option` (`bs_options`). Meta via `Concerns/HasMeta` (`meta()`, `setMeta()`). |
| `app/Services` | Business logic: `BookingRules`, `BookingService`, `GreenService` (greens, closed days, direction of play), `GreenManager` (add/rename/delete greens), `DaySheet`, `GreensOverview`, `RinkUtilisation` (heatmap, and `range()` for the week/month/quarter/year periods), `MemberUsage` (hours per member for the Members "Use of rinks" tab). |
| `app/Support/Settings.php` | Cached site settings from `bs_options`. Always read and write settings through it. |
| `app/Filament` | Secretary / admin panel at `/admin`: resources (Members, Bookings, Events, Rinks) and pages (Greens, Utilisation, SiteSettings, Maintenance). Pages and resources are auto-discovered. |
| `app/Http/Controllers` | Member pages (custom Blade), plus the Secretary's close/open green, direction and day sheet. |
| `resources/views/filament/pages` | Blade views of the Filament pages. |
| `tests/Feature`, `tests/Unit` | Pest tests (MySQL/MariaDB, `RefreshDatabase`). |
| `tests/Concurrency` | Parallel booking test (truncates the database instead of transactions). |
| `tests/Browser` | Playwright flows at phone and desktop widths, run by `scripts/e2e.sh`. |

Conventions:
- A rink's green is the prefix of its name: `A-1` is on green `A`. Times are stored as local wall-clock times
  (`Africa/Johannesburg`); rink times in seconds, reservation times as `H:i:s`.
- Privileges: `User::hasPrivilege()`; admin has all, `assist` gets `allow.<privilege>` meta. Panel entry is
  `admin.see-menu`; then `admin.user`, `admin.booking`, `admin.event`, `admin.config`. Every Filament page
  sets `canAccess()`.
- Settings stored as lines in `bs_options`: closed days `service.greens.closed` (`YYYY-MM-DD:A`), direction of
  play `service.greens.direction` (`YYYY-MM-DD:A:NS|EW`, a year and a bit of history kept for utilisation).
  `GreenManager` rename/delete must carry both.
- Write plain, short user-facing text in British English ("utilisation", "colour").
- One look for both halves (PLAN.md decision 6): member pages mirror the panel's topbar, sidebar and phone
  drawer (`layouts/app.blade.php`) and use its colours. Colours come from `App\Support\Theme` (panel) and
  the CSS variables in `public/css/app.css` (members), with `.dark` variants; `ThemeTest` keeps the
  primary palette in step. Use Heroicons via `svg('heroicon-o-...')` in member views.
- Docblocks explain *why*; PHPDoc array shapes keep Larastan level 6 happy.

## Session setup (Claude Code on the web)

`.claude/hooks/session-start.sh` runs on every session start and prepares everything:
1. Installs and starts **MariaDB**, creates databases `bowlsbuddy` (dev), `bowlsbuddy_test` (Pest) and
   `bowlsbuddy_e2e` (Playwright) and user `bb` / `bb` (the credentials in `phpunit.xml` and `.env.example`).
2. Runs `composer install --prefer-source`. The egress proxy returns 403 on GitHub's zipball API, so dist
   downloads fail; packages are cloned instead. `phpstan/phpstan` has no source in the lock file, so the hook
   clones it at the locked commit and seeds Composer's cache with the zip.
3. `npm install` for `@playwright/test`. Chromium is preinstalled at `/opt/pw-browsers/chromium`; never run
   `playwright install`.
4. Creates `.env` from `.env.example`, generates the key and migrates the dev database.

Environment pitfalls:
- `GITHUB_TOKEN` in the container is the placeholder `proxy-injected`. Never give it to Composer
  (`composer config github-oauth...`); Composer rejects it and every install then fails.
- Composer runs as root, so `COMPOSER_ALLOW_SUPERUSER=1` is set.
- MariaDB does not survive a container restart; rerun the hook (or `mysqld_safe --user=mysql &`) if
  `mysqladmin ping` fails.
- Never use `pkill -f` with a pattern that appears in the same command line; it kills its own shell.

## Checks (run all before committing)

```bash
vendor/bin/pint --test                                   # style (vendor/bin/pint to fix)
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
vendor/bin/pest                                          # all suites, about 25 s
vendor/bin/pest tests/Feature/UtilisationTest.php        # one file
scripts/e2e.sh                                           # Playwright, own database and server on :8901
```

`composer check` runs the first three. CI (`.github/workflows/ci.yml`) runs them on MySQL 8 plus
`composer audit`, on every push and pull request.

## Testing notes

- Tests seed `Database\Seeders\ClubSeeder` (greens A and B, rinks A-1 to B-6, 12:00 to 17:00 in 1-hour
  slots) and freeze time with `Carbon::setTestNow()`.
- Helpers in `tests/Pest.php`: `rink('A-1')`, `member()`, `staff('admin.see-menu', ...)`, `booked(...)`,
  `blockedBy(...)`, `slot(...)`.
- Filament pages and actions: `Livewire::test(Page::class)->call(...)->assertSet(...)`, or
  `->callAction('name')->assertNotified('Title')`.
- Only one successful `actingAs(...)->get('/admin/...')` per test after a forbidden one: a second
  request in the same test, after a 403 in the panel, fails with a Livewire `Redirector` type error. Split
  such checks into separate tests.

## Filament and Blade pitfalls

- The panel's CSS is precompiled; Tailwind classes that Filament itself does not use have no effect in
  custom page views. Use Filament components (`x-filament::section`, `x-filament::button`) and a small
  scoped `<style>` block for anything else (see `utilisation.blade.php`), with `.dark` variants.
- Import classes in Blade with `@use('App\\...')` at the top of the view, not `use` inside `@php`.
- A tab that needs its own columns (Members "Use of rinks"): the tab's `modifyQueryUsing` adds the data, and
  columns use `->visible()`/`->hidden()` closures on `$livewire`; see `MemberResource::showingUsage()`.
- Livewire `assertSee()` fails on text that Blade breaks across source lines; assert the parts.
- Check a new page visually: `php artisan serve`, log in at `/admin` as `secretary@example.com` (password
  from `CLUB_ADMIN_PASSWORD` when seeding), and screenshot with Playwright at 390 px and desktop width, light
  and dark.

## Git workflow

- Work on the branch the session names; commit with clear messages; `git push -u origin <branch>`.
- Changes reach `main` through a pull request once CI is green.

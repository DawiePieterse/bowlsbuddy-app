# Deploying to InfinityFree

InfinityFree has no command line, Composer or scheduled jobs, so the app is built on your own computer (or in
a Claude session) and uploaded through the browser File Manager. Everything below works without SSH.

## What gets built

```bash
scripts/build-infinityfree.sh                 # updates: app.zip + htdocs.zip
scripts/build-infinityfree.sh --install-sql   # first install: also install.sql
```

Output goes to `build/infinityfree/`:

| File | Where it goes |
|---|---|
| `app.zip` | Extract in the **account root**, next to `htdocs/` (not inside it) |
| `htdocs.zip` | Extract **inside** `htdocs/` |
| `install.sql` | First install only: import in phpMyAdmin |

`--install-sql` needs a scratch database it can empty (`BUILD_DB_DATABASE`, `BUILD_DB_USERNAME`,
`BUILD_DB_PASSWORD`, `BUILD_DB_HOST`) and uses the `CLUB_*` settings (see `.env.example`). Set
`CLUB_ADMIN_PHONE` to the Secretary's mobile number (their login) and `CLUB_ADMIN_PASSWORD` to choose their
first password, or note the random one it prints.

## First install

1. **Database:** InfinityFree control panel > MySQL Databases > create one. Open phpMyAdmin for it and
   import `install.sql`.
2. **Files:** in the File Manager, delete everything inside `htdocs/`. Upload `htdocs.zip` into `htdocs/`
   and extract it. Upload `app.zip` to the account root and extract it there.
3. **Settings:** copy `.env.infinityfree.example` to `.env` in the account root and fill in the database
   details and `APP_KEY` (run `php artisan key:generate --show` locally and paste the result).
4. **PHP version:** in the control panel, select PHP 8.2 or newer.
5. Open the site and log in at `/admin` with the Secretary's mobile number.

## Updates

1. Build without `--install-sql`.
2. Upload and extract `app.zip` (account root) and `htdocs.zip` (`htdocs/`), replacing the old files.
   Keep `.env` and the `storage/` folder.
3. Log in at `/admin` > **Maintenance** > **Run database updates** if any are listed.

## Backups

Until the Download backup button arrives (Phase 5), export the database weekly from phpMyAdmin.

## Moving to paid hosting

When the first club signs up (see docs/PLAN.md, section 9.2): export the database, import it on the new host,
upload the same build, copy `.env` with the new database details, and point the domain at the new host.

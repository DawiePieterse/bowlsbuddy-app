# Deploying to Afrihost

Afrihost Bronze Pro Linux hosting (cPanel, account `bowlsbg5n9w0` on `thula.aserv.co.za`) has 20 databases and
100 subdomains, and no Terminal. Each club gets its own subdomain, folder and database, all running the same
build, uploaded through the cPanel File Manager. Everything below works without SSH.

| Club | Subdomain | Folder (document root) | Database |
|---|---|---|---|
| LCE | `lce.bowlsbuddy.co.za` | `bowlsbuddy-lce/` (`bowlsbuddy-lce/public`) | e.g. `bowlsbg5n9w0_lce` |

The app's code and `.env` sit outside `public_html`, so only `public/` can be reached from the web.

## What gets built

```bash
scripts/build-afrihost.sh                 # updates: bowlsbuddy.zip
scripts/build-afrihost.sh --install-sql   # first install: also install.sql
```

Output goes to `build/afrihost/`. `--install-sql` takes the same `BUILD_DB_*` and `CLUB_*` settings as the
InfinityFree build (see docs/DEPLOY.md).

## New club (first install)

1. **Subdomain:** cPanel > Domains > Create A New Domain. Enter `<club>.bowlsbuddy.co.za`, untick
   **Share document root**, and set the document root to `bowlsbuddy-<club>/public`.
2. **PHP:** cPanel > MultiPHP Manager. Tick the new subdomain and choose PHP 8.3 (8.2 or newer).
3. **Database:** cPanel > Database Wizard. Create the database and a user with **ALL PRIVILEGES**, and note
   the full names (with the `bowlsbg5n9w0_` prefix) and the password. Open phpMyAdmin, select the database and
   import `install.sql`.
4. **Files:** cPanel > File Manager > `bowlsbuddy-<club>/`. Upload `bowlsbuddy.zip` there and extract it
   (it fills in the empty `public/` folder cPanel made). Delete the zip afterwards.
5. **Settings:** in the same folder, create `.env` from `.env.afrihost.example`: set `APP_URL`, the database
   details (`DB_HOST=localhost`) and `APP_KEY`. File Manager hides dot files unless **Settings > Show Hidden
   Files** is ticked.
6. **HTTPS:** wait until cPanel > SSL/TLS Status shows a valid certificate for the subdomain (AutoSSL; run it
   there if needed). Logins need it, because cookies are secure-only. Then turn on **Force HTTPS Redirect**
   for the subdomain in cPanel > Domains.
7. Open the site, log in at `/admin` as the Secretary and change the password.

## Updates

1. Build without `--install-sql`.
2. For each club: upload `bowlsbuddy.zip` to `bowlsbuddy-<club>/` and extract it, replacing the old files.
   `.env` and uploaded files in `storage/` are kept (the zip has neither).
3. Log in at `/admin` > **Maintenance** > **Run database updates** if any are listed.

## Backups

cPanel > Backup: download a database backup for each club weekly (and after big changes), until the admin
panel's Download backup button arrives (Phase 5). Ask Afrihost whether the package also includes daily backups.

## If Afrihost enables SSH

Deploys can then use git and Composer on the server (cPanel > Git Version Control) and `php artisan migrate`,
instead of zip uploads. The folder layout above stays the same.

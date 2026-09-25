# Deploying to Afrihost

Each club gets its own subdomain, folder and database on Afrihost cPanel hosting, all running the same build,
uploaded through the cPanel File Manager. Everything below works without SSH. No passwords or keys are kept
in this file.

## The account

| What | Value |
|---|---|
| Package | Afrihost **Bronze Pro Linux Hosting** (R135 pm, bought 24 Sep 2026) |
| Domain | `bowlsbuddy.co.za` (registered with the package; expires 24 Sep 2027) |
| Managed from | Afrihost ClientZone (`clientzone.afrihost.com`) > Web Hosting > bowlsbuddy.co.za |
| cPanel | ClientZone > Website Manager > Log into Website Manager (cPanel 138, Jupiter theme) |
| Server | `thula.aserv.co.za`, shared IP `197.242.159.147` |
| cPanel user / home | `bowlsbg5n9w0` / `/home/bowlsbg5n9w0` |
| Limits | 7.81 GB disk, 20 MySQL databases, 100 subdomains, 3 FTP accounts, unlimited traffic |
| Database server | MariaDB 10.11 (utf8mb4), `DB_HOST=localhost`; names and users get the `bowlsbg5n9w0_` prefix |
| PHP | Set per domain in MultiPHP Manager. Use **PHP 8.3 (ea-php83)**, the server default. Avoid the `alt-php` builds (no extension picker here) and 8.5 (untested). |
| Web server | LiteSpeed (Laravel's `public/.htaccess` works as is) |
| Support | WhatsApp (7AM-7PM weekdays, 7AM-6PM weekends), phone 011 612 7200, or a ticket in ClientZone |

Package choice: Bronze Pro was picked over Platinum (R109, 5 databases) because one database per club means
room for about 20 clubs for R26 more a month. Silver Pro (R179, 100 databases) is the next step up.

### What this cPanel has, and hasn't

- **No Terminal**, so no Composer or `artisan` on the server. **SSH Access** only manages keys; whether shell
  access can be switched on is still to be asked (see [Open items](#open-items)).
- **Git Version Control**, **Cron Jobs**, **Backup** / **Backup Wizard**, **Remote Database Access**,
  **MultiPHP INI Editor** (memory and upload limits), **Database Wizard**, phpMyAdmin 5.2.
- Softaculous, SitePad and Sitejet are there but not used; don't install apps over a club folder.

## Clubs

| Club | Subdomain | Folder (document root) | Database and user | PHP | Status |
|---|---|---|---|---|---|
| LCE | `lce.bowlsbuddy.co.za` | `bowlsbuddy-lce/` (`bowlsbuddy-lce/public`) | `bowlsbg5n9w0_lce` | 8.3 | Live 25 Sep 2026 (HTTPS, Secretary login works) |

The app's code and `.env` sit outside `public_html`, so only `public/` can be reached from the web. This
server allows document roots outside `public_html` (cPanel even suggests `/home/<user>/<domain>`).
`public_html` belongs to `bowlsbuddy.co.za` itself and is still empty.

## What gets built

```bash
scripts/build-afrihost.sh                 # updates: bowlsbuddy.zip
scripts/build-afrihost.sh --install-sql   # first install: also install.sql
```

Output goes to `build/afrihost/`. `--install-sql` takes the same `BUILD_DB_*` and `CLUB_*` settings as the
InfinityFree build (see docs/DEPLOY.md). Set `CLUB_ADMIN_PHONE` to the Secretary's mobile number: it is their
login.

## New club (first install)

1. **Subdomain:** cPanel > Domains > Create A New Domain. Enter `<club>.bowlsbuddy.co.za`, untick
   **Share document root**, and set the document root to `bowlsbuddy-<club>/public`.
2. **PHP:** cPanel > MultiPHP Manager. Tick the new subdomain and choose PHP 8.3 (8.2 or newer).
3. **Database:** cPanel > Database Wizard. Create the database and a user (both `<club>`) with **ALL
   PRIVILEGES**, and note the full names (with the `bowlsbg5n9w0_` prefix) and the password. Open phpMyAdmin,
   click the database on the left, then Import (on a narrow screen the tabs are behind the ☰ button) and
   import `install.sql`. It should leave 16 tables (`bb_*` and `bs_*`).
4. **Files:** cPanel > File Manager > `bowlsbuddy-<club>/`. Upload `bowlsbuddy.zip` there and extract it
   (it fills in the empty `public/` folder cPanel made). Delete the zip afterwards.
5. **Settings:** in the same folder, create `.env` from `.env.afrihost.example`: set `APP_URL`, the database
   details (`DB_HOST=localhost`) and `APP_KEY`. File Manager hides dot files unless **Settings > Show Hidden
   Files** is ticked.
6. **HTTPS:** wait until cPanel > SSL/TLS Certificates > **Status** shows a valid (not self-signed)
   certificate for the subdomain. AutoSSL runs by itself about once a day; the Run AutoSSL control, if Afrihost
   shows it, is on that Status tab (possibly behind the ⚙ button). Logins need HTTPS, because cookies are
   secure-only. Then turn on **Force HTTPS Redirect** for the subdomain in cPanel > Domains, not before.
   The green padlocks on the **Installation** tab only mean a certificate is installed; check its Issuer.
7. Open the site and log in at `/admin` with the Secretary's mobile number (`CLUB_ADMIN_PHONE` in the build).
8. Set the direction of play for each green (admin > Direction of play).

## Updates

1. Build without `--install-sql`.
2. For each club: upload `bowlsbuddy.zip` to `bowlsbuddy-<club>/` and extract it, replacing the old files.
   `.env` and uploaded files in `storage/` are kept (the zip has neither).
3. Log in at `/admin` > **Maintenance** > **Run database updates** if any are listed.

## Backups

cPanel > Backup: download a database backup for each club weekly (and after big changes), until the admin
panel's Download backup button arrives (Phase 5). Ask Afrihost whether the package also includes daily backups.

## Troubleshooting

- **AutoSSL: "bowlsbuddy.co.za is unmanaged. Verify this domain's registration and authoritative nameserver
  configuration"** (24 Sep 2026): the new `.co.za` was not live in DNS yet. It needs the registration to
  go through (hours, up to a day); nothing to change on the server. If it lasts beyond a day, ask Afrihost to
  check the registration and nameservers and to run AutoSSL.
  On 25 Sep it had cleared by itself overnight: AutoSSL issued certificates for every domain (valid three
  months, renewed automatically).
- **"Your domain is at risk" / self-signed** on the cPanel home page: same cause; don't buy a certificate.
- **Server error on the site:** read `bowlsbuddy-<club>/storage/logs/laravel.log` in File Manager (View).
- **Files starting with a dot are missing** in File Manager: Settings > Show Hidden (dotfiles).
- **MultiPHP Manager's version list jumps back to PHP 5.2** after each apply: check the choice before tapping
  Apply again.

## Open items

- [x] HTTPS for `lce.bowlsbuddy.co.za` (AutoSSL, 25 Sep 2026).
- [x] Force HTTPS Redirect for `lce.bowlsbuddy.co.za` and `bowlsbuddy.co.za` (cPanel > Domains, 25 Sep 2026).
- [x] First login at `https://lce.bowlsbuddy.co.za/admin` as the Secretary (25 Sep 2026).
- [ ] Move LCE to mobile-number logins. The live database has the old `secretary@example.com` login (its
  password was shared in a chat, so treat it as exposed) and no phone numbers, and the new build logs in by
  mobile number only. LCE has no members yet, so reinstall rather than update:
  1. Build with the Secretary's real number and a new password (not shared in any chat):
     `CLUB_ADMIN_PHONE=<number> CLUB_ADMIN_PASSWORD=<password> scripts/build-afrihost.sh --install-sql`.
  2. phpMyAdmin > `bowlsbg5n9w0_lce` > Structure: tick all tables > Drop. Then import the new `install.sql`
     (16 tables, including `bs_green_directions`).
  3. Upload and extract `bowlsbuddy.zip` in `bowlsbuddy-lce/` as for an update (`.env` stays).
  4. Log in at `/admin` with the mobile number, then set the direction of play (admin > Direction of play).
- [ ] Ask Afrihost: can SSH shell access be enabled (and on which port)? Are `intl` and `zip` enabled for
  ea-php83? Are daily backups included?
- [ ] Decide what `bowlsbuddy.co.za` itself shows (a landing page or a redirect); its `public_html` is empty.

## If Afrihost enables SSH

Deploys can then use git and Composer on the server (cPanel > Git Version Control) and `php artisan migrate`,
instead of zip uploads. The folder layout above stays the same.

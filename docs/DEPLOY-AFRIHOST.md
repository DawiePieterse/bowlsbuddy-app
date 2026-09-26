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

- **Jailed SSH is enabled** (Afrihost support, 26 Sep 2026): enough for `php artisan`, git and a
  `composer.phar`. See [SSH](#ssh).
- **Git Version Control**, **Cron Jobs**, **Backup** / **Backup Wizard**, **Remote Database Access**,
  **MultiPHP INI Editor** (memory and upload limits), **Database Wizard**, phpMyAdmin 5.2.
- The **intl** and **zip** extensions are enabled for PHP 8.3 (ea-php83) — confirmed by support, 26 Sep 2026.
- Softaculous, SitePad and Sitejet are there but not used; don't install apps over a club folder.

### SSH

Enabled by Afrihost support on 26 Sep 2026 (jailed shell; full SSH would need a dedicated server).

| What | Value |
|---|---|
| Host | `thula.aserv.co.za` (or the shared IP `197.242.159.147`), port **24** |
| User / password | `bowlsbg5n9w0` / the cPanel password (reset: ClientZone > Hosting > the domain > Website Manager > Reset Password) |
| Login | **Password only** — support says key-only login is not supported on this server (even though cPanel > SSH Access can import keys) |
| From where | Only **South African IP addresses** are allowed. From elsewhere (or a cloud session), use **cPanel > Terminal**, which now works and needs no allowlisting |
| Blocked? | Send support your public IP, `ping`/`traceroute` to the domain, and `ssh -v bowlsbg5n9w0@thula.aserv.co.za -p24` output, and ask for an allowed-host entry |

## Clubs

| Club | Subdomain | Folder (document root) | Database and user | PHP | Status |
|---|---|---|---|---|---|
| LCE | `lce.bowlsbuddy.co.za` | `bowlsbuddy-lce/` (`bowlsbuddy-lce/public`) | `bowlsbg5n9w0_lce` | 8.3 | Live 25 Sep 2026 (HTTPS, Secretary login works) |

The app's code and `.env` sit outside `public_html`, so only `public/` can be reached from the web. This
server allows document roots outside `public_html` (cPanel even suggests `/home/<user>/<domain>`).
`public_html` belongs to `bowlsbuddy.co.za` itself and holds only the landing page (`landing/index.html`).

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
3. **Database:** cPanel > Database Wizard. Create the database and a user (both `<club>`) with **ALL
   PRIVILEGES**, and note the full names (with the `bowlsbg5n9w0_` prefix) and the password. Open phpMyAdmin,
   click the database on the left, then Import (on a narrow screen the tabs are behind the ☰ button) and
   import `install.sql`. It should report about 159 queries and leave 15 tables (`bb_*` and `bs_*`).
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
7. Open the site, log in at `/admin` as the Secretary and change the password.

## Updates

1. Build without `--install-sql`.
2. For each club: upload `bowlsbuddy.zip` to `bowlsbuddy-<club>/` and extract it, replacing the old files.
   `.env` and uploaded files in `storage/` are kept (the zip has neither).
3. Log in at `/admin` > **Maintenance** > **Run database updates** if any are listed.

## Backups

This server does **not** use AfriBackup: it runs Afrihost's newer backup software (**Afrires**), which keeps
the past **14 days** of websites, email **and MySQL databases** (support, 26 Sep 2026). There is nothing to
see or do in cPanel: **restores are done by Afrihost support only** (they need root access), so a restore is
a ticket to `hosting@afrihost.com`.

Because their restore is out of our hands and their history is only two weeks, still download a database
backup for each club weekly and before big changes, and keep a copy older than two weeks. Use the admin
panel's **Maintenance > Download backup** button (an SQL dump; restore by importing it in phpMyAdmin), or
cPanel > Backup.

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
- **`bowlsbuddy.co.za` shows a red "Has been registered on behalf of a client" page** although `/index.html`
  shows the landing page: Afrihost's placeholder in `public_html` is served first. Edit `public_html/.htaccess`
  (it already holds cPanel's PHP blocks; leave them) and add `DirectoryIndex index.html` below the last `END`
  line. Done for `bowlsbuddy.co.za` on 25 Sep 2026.
- **File Manager shows "Data error."** instead of a folder's files (seen on `public_html`): tap Reload or the
  folder in the tree on the left. Uploads to that folder still work; check the destination on the upload page.
- **MultiPHP Manager's version list jumps back to PHP 5.2** after each apply: check the choice before tapping
  Apply again.

## Open items

- [x] HTTPS for `lce.bowlsbuddy.co.za` (AutoSSL, 25 Sep 2026).
- [x] Force HTTPS Redirect for `lce.bowlsbuddy.co.za` and `bowlsbuddy.co.za` (cPanel > Domains, 25 Sep 2026).
- [x] First login at `https://lce.bowlsbuddy.co.za/admin` as the Secretary (25 Sep 2026).
- [x] Profile page in the admin panel (avatar menu, top right > Profile, `/admin/profile`): name, email and
  password, saved to `bs_users.alias` / `email` / `pw`. Changing the email or password asks for the current
  password.
- [x] Profile page deployed to LCE and the Secretary's email and password changed there, replacing the exposed
  seeded `secretary@example.com` login (25 Sep 2026).
- [x] Automatic backups: this server runs Afrires, 14 days of files, email and MySQL databases, restored
  only by support (see [Backups](#backups)).
- [x] Answered by `hosting@afrihost.com` (26 Sep 2026): jailed SSH enabled (see [SSH](#ssh); password only,
  South African IPs, cPanel > Terminal always works); backups are Afrires, databases included,
  restores via support; `intl` and `zip` already enabled for ea-php83.
- [x] `bowlsbuddy.co.za` shows a simple landing page (live 25 Sep 2026): upload `landing/index.html` to `public_html/`. Add each new
  club to its Clubs list.

## Now that SSH is enabled

Deploys can use git and Composer on the server (cPanel > Git Version Control, or clone over HTTPS in the
Terminal) and `php artisan migrate`, instead of zip uploads. The folder layout above stays the same. First
time in the Terminal:

```bash
cd ~/bowlsbuddy-lce
php -v                       # should be PHP 8.3 (ea-php83)
php artisan migrate --force  # instead of the Maintenance page
```

Composer is not preinstalled in the jail: upload `composer.phar` once (`php composer.phar --version`), or
keep shipping `vendor/` in the zip build, which stays the simplest update path.

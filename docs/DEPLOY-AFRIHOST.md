# Deploying to Afrihost

Bowls Buddy runs on Afrihost's Linux web hosting, which uses **cPanel**. The app is built on your own
computer (or in a Claude session) and goes up as two zips plus one database import, through the cPanel File
Manager and phpMyAdmin. Nothing below needs SSH.

## Before you start

Check these in cPanel (or ask Afrihost support) before building anything:

| Check | Where in cPanel | Needed |
|---|---|---|
| PHP version | **MultiPHP Manager** or **Select PHP Version** | 8.2 or newer (8.3 preferred) |
| PHP extensions | **Select PHP Version** > Extensions | `intl`, `mbstring`, `pdo_mysql`, `zip`, `fileinfo`, `openssl` |
| Database | **MySQL Databases** | MySQL 8 or MariaDB 10.6+ |
| HTTPS | **SSL/TLS Status** | A certificate on the domain (AutoSSL / Let's Encrypt) |

## What gets built

```bash
scripts/build-afrihost.sh                 # updates: app.zip + public_html.zip
scripts/build-afrihost.sh --install-sql   # first install: also install.sql
```

Output goes to `build/afrihost/`:

| File | Where it goes on Afrihost |
|---|---|
| `app.zip` | Extract in your **home folder** (`/home/<cpanel-user>/`), next to `public_html/`, not inside it |
| `public_html.zip` | Extract **inside** `public_html/` |
| `install.sql` | First install only: import in phpMyAdmin |

`public/index.php` loads the app from the folder above it, so `public_html/` must sit directly next to the
extracted `app.zip` files (`vendor/`, `bootstrap/`, `storage/`...).

`--install-sql` needs a scratch database it can empty (`BUILD_DB_DATABASE`, `BUILD_DB_USERNAME`,
`BUILD_DB_PASSWORD`, `BUILD_DB_HOST`) and uses the `CLUB_*` settings (see `.env.example`). Set
`CLUB_ADMIN_PASSWORD` to choose the Secretary's first password, or note the random one it prints.

## First install

1. **Database:** cPanel > **MySQL Databases**. Create a database and a user, then add the user to the
   database with **All Privileges**. cPanel adds your account name to the front of both names (for example
   `lcebowls_bb` and `lcebowls_bbuser`); use the full names in `.env`.
2. **Import:** cPanel > **phpMyAdmin** > select the new database > **Import** > `install.sql`.
3. **Files:** cPanel > **File Manager**. Move anything already in `public_html/` (the Afrihost holding page)
   out of the way. Upload `public_html.zip` into `public_html/` and extract it. Upload `app.zip` to the home
   folder and extract it there.
4. **Settings:** copy `.env.afrihost.example` to `.env` in the home folder and fill in:
   - `APP_URL`: the club's address, with `https://`
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: the full names from step 1
   - `APP_KEY`: run `php artisan key:generate --show` locally and paste the result
5. **PHP:** select PHP 8.2 or newer for the domain and switch on the extensions listed above.
6. **Permissions:** `storage/` and `bootstrap/cache/` must be writable (755 folders, 644 files is normal
   on cPanel; don't use 777).
7. **HTTPS:** make sure the certificate is active, then open the site. Log in at `/admin` as the Secretary
   and change the password.

## Updates

1. Build without `--install-sql`.
2. Upload and extract `app.zip` (home folder) and `public_html.zip` (`public_html/`), replacing the old files.
   Keep `.env` and the `storage/` folder.
3. Log in at `/admin` > **Maintenance** > **Run database updates** if any are listed.

## Backups

Export the database weekly from phpMyAdmin, and check which automatic backups your Afrihost package includes
(cPanel **Backup**, or JetBackup if it is listed). Test one restore on a local copy.

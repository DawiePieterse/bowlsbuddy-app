#!/usr/bin/env bash
#
# Builds the files to upload to InfinityFree, which has no command line (see docs/DEPLOY.md):
#
#   app.zip      everything except public/  -> extract in the account root, next to htdocs/
#   htdocs.zip   the contents of public/     -> extract inside htdocs/
#   install.sql  (with --install-sql) empty database with the club set up -> import once in phpMyAdmin
#
# Only committed files are included. Usage:
#
#   scripts/build-infinityfree.sh [--install-sql] [output-dir]
#
# --install-sql needs a scratch MySQL/MariaDB database, given by BUILD_DB_HOST, BUILD_DB_DATABASE,
# BUILD_DB_USERNAME and BUILD_DB_PASSWORD (it is emptied), and the club settings from CLUB_* variables
# (CLUB_ADMIN_PASSWORD sets the Secretary's first password; otherwise a random one is printed).

set -euo pipefail

INSTALL_SQL=false
if [[ "${1:-}" == "--install-sql" ]]; then
    INSTALL_SQL=true
    shift
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$(mkdir -p "${1:-$ROOT/build/infinityfree}" && cd "${1:-$ROOT/build/infinityfree}" && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "Staging committed files..."
git -C "$ROOT" archive HEAD | tar -x -C "$STAGE"

echo "Installing production dependencies..."
(cd "$STAGE" && composer install --no-dev --no-interaction --no-progress --prefer-dist \
    --optimize-autoloader --classmap-authoritative --quiet)

# Drop package tests and docs that git-cloned packages bring along.
php "$ROOT/scripts/strip-export-ignored.php" "$STAGE/vendor"

# Not needed on the server.
rm -rf "$STAGE"/{tests,.github,docs,scripts,phpunit.xml,phpstan.neon,.env.example,.editorconfig,.gitattributes,package.json,vite.config.js}

if $INSTALL_SQL; then
    : "${BUILD_DB_DATABASE:?Set BUILD_DB_DATABASE (a scratch database that will be emptied)}"
    echo "Creating install.sql from a fresh database..."
    (
        cd "$STAGE"
        export APP_ENV=build APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" \
            DB_CONNECTION=mysql DB_HOST="${BUILD_DB_HOST:-127.0.0.1}" DB_DATABASE="$BUILD_DB_DATABASE" \
            DB_USERNAME="${BUILD_DB_USERNAME:-root}" DB_PASSWORD="${BUILD_DB_PASSWORD:-}" \
            CACHE_STORE=array SESSION_DRIVER=array
        php artisan db:wipe --force --quiet
        php artisan migrate --force --quiet
        php artisan db:seed --class='Database\Seeders\ClubSeeder' --force
    )
    mysqldump --host="${BUILD_DB_HOST:-127.0.0.1}" --user="${BUILD_DB_USERNAME:-root}" \
        ${BUILD_DB_PASSWORD:+--password="$BUILD_DB_PASSWORD"} --single-transaction --skip-comments \
        --no-tablespaces "$BUILD_DB_DATABASE" > "$OUT/install.sql"
fi

echo "Zipping..."
rm -f "$OUT/app.zip" "$OUT/htdocs.zip"
(cd "$STAGE/public" && zip -qr "$OUT/htdocs.zip" .)
# Composer falls back to git clones where GitHub zip downloads are blocked (as in Claude sessions), so keep
# .git history and package test suites out of the upload.
(cd "$STAGE" && zip -qr -9 "$OUT/app.zip" . -x 'public/*' '*/.git/*' '*/.git' 'vendor/*/*/tests/*' 'vendor/*/*/.github/*')

echo "Checking..."
app_listing="$(unzip -l "$OUT/app.zip")"
htdocs_listing="$(unzip -l "$OUT/htdocs.zip")"
fail() { echo "BUILD FAILED: $1" >&2; exit 1; }
grep -q ' vendor/autoload\.php$' <<<"$app_listing" || fail "app.zip has no vendor/autoload.php"
grep -q ' artisan$' <<<"$app_listing" || fail "app.zip has no artisan"
grep -q '/\.git/' <<<"$app_listing" && fail "app.zip contains .git/ folders"
grep -q ' \.env$' <<<"$app_listing" && fail "app.zip contains a .env file"
grep -q ' index\.php$' <<<"$htdocs_listing" || fail "htdocs.zip has no index.php"
grep -q ' \.htaccess$' <<<"$htdocs_listing" || fail "htdocs.zip has no .htaccess"
grep -q ' css/filament/' <<<"$htdocs_listing" || fail "htdocs.zip has no Filament assets"

echo "Done: $OUT"
ls -lh "$OUT"

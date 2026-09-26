#!/usr/bin/env bash
#
# Runs the Playwright booking-flow tests against a throwaway database.
# Needs: MySQL/MariaDB reachable with the phpunit.xml credentials, Chromium for Playwright.
set -euo pipefail

cd "$(dirname "$0")/.."

export DB_DATABASE="${E2E_DB_DATABASE:-bowlsbuddy_e2e}"
export DB_USERNAME="${DB_USERNAME:-bb}"
export DB_PASSWORD="${DB_PASSWORD:-bb}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export APP_ENV=testing
export CACHE_STORE=array
export SESSION_DRIVER=file
export CLUB_ADMIN_PASSWORD=e2e-admin-password

mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_DATABASE" 2>/dev/null \
  || mariadb -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_DATABASE"

php artisan migrate:fresh --force --no-interaction
php artisan db:seed --class=Database\\Seeders\\ClubSeeder --force --no-interaction

PORT="${E2E_PORT:-8901}"
php artisan serve --host=127.0.0.1 --port="$PORT" >storage/logs/e2e-serve.log 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null || true' EXIT

for _ in $(seq 1 30); do
    curl -fsS "http://127.0.0.1:$PORT" >/dev/null 2>&1 && break
    sleep 0.5
done

E2E_BASE_URL="http://127.0.0.1:$PORT" E2E_CHROMIUM="${E2E_CHROMIUM:-/opt/pw-browsers/chromium}" npx playwright test "$@"

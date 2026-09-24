#!/usr/bin/env bash
#
# Builds the file to upload to Afrihost cPanel hosting, which has no command line (see docs/DEPLOY-AFRIHOST.md):
#
#   bowlsbuddy.zip  the whole app, public/ included -> extract inside the club's folder (e.g. bowlsbuddy-lce/),
#                   whose subdomain has its document root set to that folder's public/
#   install.sql     (with --install-sql) empty database with the club set up -> import once in phpMyAdmin
#
# It reuses the InfinityFree build and puts htdocs.zip back into public/. Usage:
#
#   scripts/build-afrihost.sh [--install-sql] [output-dir]
#
# --install-sql takes the same BUILD_DB_* and CLUB_* variables as scripts/build-infinityfree.sh.

set -euo pipefail

ARGS=()
if [[ "${1:-}" == "--install-sql" ]]; then
    ARGS+=(--install-sql)
    shift
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$(mkdir -p "${1:-$ROOT/build/afrihost}" && cd "${1:-$ROOT/build/afrihost}" && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

"$ROOT/scripts/build-infinityfree.sh" "${ARGS[@]}" "$WORK/infinityfree"

echo "Combining into one zip..."
mkdir -p "$WORK/app/public"
unzip -q "$WORK/infinityfree/app.zip" -d "$WORK/app"
unzip -q "$WORK/infinityfree/htdocs.zip" -d "$WORK/app/public"
rm -f "$OUT/bowlsbuddy.zip" "$OUT/install.sql"
(cd "$WORK/app" && zip -qr -9 "$OUT/bowlsbuddy.zip" .)
[[ -f "$WORK/infinityfree/install.sql" ]] && cp "$WORK/infinityfree/install.sql" "$OUT/"

echo "Checking..."
listing="$(unzip -l "$OUT/bowlsbuddy.zip")"
fail() { echo "BUILD FAILED: $1" >&2; exit 1; }
grep -q ' vendor/autoload\.php$' <<<"$listing" || fail "no vendor/autoload.php"
grep -q ' public/index\.php$' <<<"$listing" || fail "no public/index.php"
grep -q ' public/\.htaccess$' <<<"$listing" || fail "no public/.htaccess"
grep -q ' public/css/filament/' <<<"$listing" || fail "no Filament assets"
grep -q ' \.env$' <<<"$listing" && fail "contains a .env file"

echo "Done: $OUT"
ls -lh "$OUT"

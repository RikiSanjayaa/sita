#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

DOMAIN="${DOMAIN:?Isi DOMAIN, contoh: DOMAIN=sita.kampus.ac.id bash deploy/aapanel-deploy.sh}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
GIT_PULL="${GIT_PULL:-true}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
RUN_QUEUE_RESTART="${RUN_QUEUE_RESTART:-true}"

export PATH="$(dirname "$PHP_BIN"):$PATH"

APP_WAS_DOWN=0

finish() {
    if [ "$APP_WAS_DOWN" -eq 1 ]; then
        "$PHP_BIN" artisan up >/dev/null 2>&1 || true
    fi
}
trap finish EXIT

step() {
    printf '\n==> %s\n' "$1"
}

require_file() {
    if [ ! -f "$1" ]; then
        printf 'File wajib tidak ditemukan: %s\n' "$1" >&2
        exit 1
    fi
}

step "Validasi project"
require_file artisan
require_file composer.json
require_file package.json
require_file .env

if grep -q '^APP_ENV=production' .env && grep -q '^APP_DEBUG=false' .env; then
    printf 'Environment production terdeteksi.\n'
else
    printf 'APP_ENV harus production dan APP_DEBUG harus false di .env.\n' >&2
    exit 1
fi

if [ "$GIT_PULL" = "true" ] && [ -d .git ]; then
    step "Update source dari git"
    git pull --ff-only
fi

step "Aktifkan maintenance mode"
if [ -f vendor/autoload.php ]; then
    "$PHP_BIN" artisan down --render="errors::503" --retry=60 || true
    APP_WAS_DOWN=1
else
    printf 'Lewati maintenance mode karena vendor/autoload.php belum ada.\n'
fi

step "Install dependency PHP production"
"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

step "Install dependency frontend"
if [ -f package-lock.json ]; then
    "$NPM_BIN" ci
else
    "$NPM_BIN" install
fi

step "Build frontend production"
"$NPM_BIN" run build

step "Siapkan storage dan permission"
mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache
"$PHP_BIN" artisan storage:link --force

step "Bersihkan cache bootstrap lama"
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan event:clear

if [ "$RUN_MIGRATIONS" = "true" ]; then
    step "Jalankan migrasi database"
    "$PHP_BIN" artisan migrate --force
fi

"$PHP_BIN" artisan cache:clear || true

step "Cache konfigurasi production"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache

if [ "$RUN_QUEUE_RESTART" = "true" ]; then
    step "Restart queue worker Laravel"
    "$PHP_BIN" artisan queue:restart
fi

step "Matikan maintenance mode"
"$PHP_BIN" artisan up
APP_WAS_DOWN=0

printf '\nDeploy selesai untuk %s.\n' "$DOMAIN"
printf 'Pastikan aaPanel Nginx root mengarah ke: %s/public\n' "$PROJECT_ROOT"

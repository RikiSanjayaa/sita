#!/usr/bin/env bash
set -u

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NODE_BIN="${NODE_BIN:-node}"
NPM_BIN="${NPM_BIN:-npm}"
DOMAIN="${DOMAIN:-}"

FAILED=0

ok() {
    printf '[OK] %s\n' "$1"
}

warn() {
    printf '[WARN] %s\n' "$1"
}

fail() {
    printf '[FAIL] %s\n' "$1"
    FAILED=1
}

need_cmd() {
    if command -v "$1" >/dev/null 2>&1; then
        ok "Command tersedia: $1"
    else
        fail "Command tidak tersedia: $1"
    fi
}

env_value() {
    key="$1"
    if [ ! -f .env ]; then
        return 0
    fi

    grep -E "^${key}=" .env | tail -n 1 | cut -d '=' -f 2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

printf 'SITA aaPanel doctor\n'
printf 'Project root: %s\n\n' "$PROJECT_ROOT"

need_cmd "$PHP_BIN"
need_cmd "$COMPOSER_BIN"
need_cmd "$NODE_BIN"
need_cmd "$NPM_BIN"
need_cmd git
need_cmd bash

if command -v "$PHP_BIN" >/dev/null 2>&1; then
    PHP_VERSION_STR="$("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || true)"
    if "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' >/dev/null 2>&1; then
        ok "PHP CLI ${PHP_VERSION_STR} memenuhi minimal 8.4"
    else
        fail "PHP CLI ${PHP_VERSION_STR:-unknown} belum memenuhi minimal 8.4"
    fi

    for extension in ctype dom fileinfo filter json mbstring openssl pcre pdo session tokenizer xml; do
        if "$PHP_BIN" -m | grep -qi "^${extension}$"; then
            ok "PHP extension aktif: ${extension}"
        else
            fail "PHP extension belum aktif: ${extension}"
        fi
    done
fi

if [ -f composer.json ]; then
    ok "composer.json ditemukan"
else
    fail "composer.json tidak ditemukan"
fi

if [ -f package.json ]; then
    ok "package.json ditemukan"
else
    fail "package.json tidak ditemukan"
fi

if [ -f package-lock.json ]; then
    ok "package-lock.json ditemukan, deploy akan memakai npm ci"
else
    warn "package-lock.json tidak ditemukan, deploy akan fallback ke npm install"
fi

if [ -f .env ]; then
    ok ".env production ditemukan"
else
    fail ".env belum ada. Salin .env.production.example menjadi .env lalu isi nilai server."
fi

if [ -f .env ]; then
    APP_ENV_VALUE="$(env_value APP_ENV)"
    APP_DEBUG_VALUE="$(env_value APP_DEBUG)"
    APP_URL_VALUE="$(env_value APP_URL)"
    APP_KEY_VALUE="$(env_value APP_KEY)"
    DB_CONNECTION_VALUE="$(env_value DB_CONNECTION)"
    QUEUE_CONNECTION_VALUE="$(env_value QUEUE_CONNECTION)"

    [ "$APP_ENV_VALUE" = "production" ] && ok "APP_ENV=production" || fail "APP_ENV harus production, sekarang: ${APP_ENV_VALUE:-kosong}"
    [ "$APP_DEBUG_VALUE" = "false" ] && ok "APP_DEBUG=false" || fail "APP_DEBUG harus false, sekarang: ${APP_DEBUG_VALUE:-kosong}"
    [ -n "$APP_KEY_VALUE" ] && ok "APP_KEY sudah terisi" || fail "APP_KEY masih kosong"
    [ -n "$APP_URL_VALUE" ] && ok "APP_URL terisi: $APP_URL_VALUE" || fail "APP_URL masih kosong"
    [ -n "$DB_CONNECTION_VALUE" ] && ok "DB_CONNECTION terisi: $DB_CONNECTION_VALUE" || fail "DB_CONNECTION masih kosong"
    [ -n "$QUEUE_CONNECTION_VALUE" ] && ok "QUEUE_CONNECTION terisi: $QUEUE_CONNECTION_VALUE" || fail "QUEUE_CONNECTION masih kosong"

    if [ "$DB_CONNECTION_VALUE" = "mysql" ] || [ "$DB_CONNECTION_VALUE" = "mariadb" ]; then
        if "$PHP_BIN" -m | grep -qi '^pdo_mysql$'; then
            ok "pdo_mysql aktif untuk ${DB_CONNECTION_VALUE}"
        else
            fail "pdo_mysql belum aktif, wajib untuk ${DB_CONNECTION_VALUE}"
        fi
    fi

    if [ -n "$DOMAIN" ] && [ -n "$APP_URL_VALUE" ]; then
        case "$APP_URL_VALUE" in
            *"$DOMAIN"*) ok "APP_URL sesuai DOMAIN=$DOMAIN" ;;
            *) warn "APP_URL tidak memuat DOMAIN=$DOMAIN. Pastikan domain production benar." ;;
        esac
    fi
fi

for dir in storage bootstrap/cache; do
    if [ -d "$dir" ]; then
        if [ -w "$dir" ]; then
            ok "Writable: $dir"
        else
            fail "Belum writable: $dir"
        fi
    else
        fail "Folder tidak ditemukan: $dir"
    fi
done

if [ -d vendor ] && [ -f artisan ] && [ -f .env ]; then
    if "$PHP_BIN" artisan --version >/dev/null 2>&1; then
        ok "Artisan bisa dijalankan"
    else
        fail "Artisan gagal dijalankan. Cek vendor, .env, dan APP_KEY."
    fi

    if "$PHP_BIN" artisan migrate:status --no-interaction >/dev/null 2>&1; then
        ok "Koneksi database dan tabel migrations bisa diakses"
    else
        fail "Database belum bisa diakses oleh Laravel. Cek DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, dan pdo_mysql."
    fi
else
    warn "Lewati cek Artisan/database karena vendor, artisan, atau .env belum lengkap."
fi

if [ "$FAILED" -eq 0 ]; then
    printf '\nDoctor selesai: server siap untuk deploy.\n'
else
    printf '\nDoctor menemukan masalah. Perbaiki item [FAIL] sebelum deploy.\n'
fi

exit "$FAILED"

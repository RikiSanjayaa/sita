#!/usr/bin/env bash
set -euo pipefail

TAG=${1:-${TAG:-sha-f7756d0}}
case "$TAG" in
    sha-[0-9a-f]*) ;;
    *) echo "TAG harus diawali sha-<commit>" >&2; exit 2 ;;
esac

ROOT=$(cd "$(dirname "$0")/.." && pwd)
PROJECT=${LAB_PROJECT:-sita-lab}
WEB_PORT=${LAB_WEB_PORT:-28088}
WORK=$(mktemp -d)
umask 077

unset SITA_APP_IMAGE SITA_WEB_IMAGE SITA_PULL_POLICY SITA_WEB_BIND SITA_WEB_PORT
SITA_WEB_PORT=$WEB_PORT
SITA_WEB_BIND=127.0.0.1

cleanup() {
    docker compose -p "$PROJECT" -f "$WORK/docker-compose.vm.yml" --env-file "$WORK/deploy.env" down -v --remove-orphans >/dev/null 2>&1 || true
    rm -rf "$WORK"
}
trap cleanup EXIT

cp "$ROOT/docker-compose.vm.yml" "$WORK/"

db_password=$(openssl rand -hex 24)
root_password=$(openssl rand -hex 24)
app_key="base64:$(openssl rand -base64 32 | tr -d '\n')"
reverb_key=$(openssl rand -hex 16)
reverb_secret=$(openssl rand -hex 32)
cat > "$WORK/app.env" <<EOF
APP_NAME=SITA
APP_ENV=production
APP_KEY=$app_key
APP_DEBUG=false
APP_URL=http://127.0.0.1:${WEB_PORT}
ASSET_URL=http://127.0.0.1:${WEB_PORT}
APP_TIMEZONE=Asia/Makassar
APP_LOCALE=id
APP_BASE_PATH=
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=sita
DB_USERNAME=sita
DB_PASSWORD=$db_password
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=false
SESSION_COOKIE=sita_lab_session
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@example.invalid
MAIL_FROM_NAME=SITA
RUN_DB_SEED=false
WEB_PUSH_ENABLED=false
SITA_ARCHIVE_ENABLED=false
INERTIA_SSR_ENABLED=false
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=sita-lab
REVERB_APP_KEY=$reverb_key
REVERB_APP_SECRET=$reverb_secret
REVERB_HOST=127.0.0.1
REVERB_PORT=$WEB_PORT
REVERB_SCHEME=http
REVERB_INTERNAL_HOST=reverb
REVERB_INTERNAL_PORT=8080
REVERB_INTERNAL_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=127.0.0.1
EOF
cat > "$WORK/db.env" <<EOF
MARIADB_DATABASE=sita
MARIADB_USER=sita
MARIADB_PASSWORD=$db_password
MARIADB_ROOT_PASSWORD=$root_password
EOF
cat > "$WORK/deploy.env" <<EOF
SITA_APP_IMAGE=ghcr.io/rikisanjayaa/sita-app:${TAG}
SITA_WEB_IMAGE=ghcr.io/rikisanjayaa/sita-web:${TAG}
SITA_PULL_POLICY=always
SITA_WEB_BIND=127.0.0.1
SITA_WEB_PORT=${WEB_PORT}
EOF

cd "$WORK"
docker compose -p "$PROJECT" --env-file deploy.env -f docker-compose.vm.yml pull
docker compose -p "$PROJECT" --env-file deploy.env -f docker-compose.vm.yml up -d --remove-orphans

ok=false
for _ in $(seq 1 48); do
    if curl -fsS --max-time 5 "http://127.0.0.1:${WEB_PORT}/up" | grep -q 'Application up' \
        && curl -fsS --max-time 5 "http://127.0.0.1:${WEB_PORT}/" >/dev/null; then
        ok=true
        break
    fi
    sleep 5
done

if [ "$ok" != true ]; then
    docker compose -p "$PROJECT" --env-file deploy.env -f docker-compose.vm.yml ps || true
    docker compose -p "$PROJECT" --env-file deploy.env -f docker-compose.vm.yml logs --no-color --tail=60 || true
    echo "LAB FAIL tag=$TAG" >&2
    exit 1
fi

echo "LAB OK tag=$TAG web=http://127.0.0.1:${WEB_PORT}"

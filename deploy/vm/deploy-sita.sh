#!/usr/bin/env bash
# Deploy SITA on the campus VM from a GHCR image tag.
# Called by /home/aji/deployments/deploy-gateway.sh, so TAG is validated there too.
set -euo pipefail

TAG=${1:-${TAG:-}}
case "$TAG" in
    sha-[0-9a-f]*) ;;
    *) echo "TAG wajib, contoh: sha-f7756d0" >&2; exit 2 ;;
esac

APP_DIR=${DEPLOY_PATH:-/home/aji/deployments/sita}
COMPOSE=docker-compose.vm.yml
IMAGE_PREFIX=${IMAGE_PREFIX:-ghcr.io/rikisanjayaa}
BACKUP_SCRIPT=${BACKUP_SCRIPT:-/home/aji/deployments/backup-prod.sh}
HEALTH_URL=${HEALTH_URL:-http://127.0.0.1:8088/up}
cd "$APP_DIR"

umask 077
ts=$(date -u +%Y%m%dT%H%M%SZ)
log() { printf '%s %s\n' "$(date -u +%FT%TZ)" "$*" | tee -a deploy.log; }

for required in deploy.env app.env db.env "$COMPOSE"; do
    [ -f "$required" ] || { log "FAIL $required tidak ada"; exit 1; }
done
[ -x "$BACKUP_SCRIPT" ] || { log "FAIL backup script tidak ada: $BACKUP_SCRIPT"; exit 1; }

previous_env="backups/deploy.env.before-$ts"
next_env="deploy.env.next.$ts"
cp deploy.env "$previous_env"

rollback() {
    log "ROLLBACK ke deploy.env sebelumnya"
    cp "$previous_env" deploy.env
    docker compose --env-file deploy.env -f "$COMPOSE" up -d --remove-orphans || true
}

if ! sed \
    -e "s#^SITA_APP_IMAGE=.*#SITA_APP_IMAGE=${IMAGE_PREFIX}/sita-app:${TAG}#" \
    -e "s#^SITA_WEB_IMAGE=.*#SITA_WEB_IMAGE=${IMAGE_PREFIX}/sita-web:${TAG}#" \
    deploy.env > "$next_env"; then
    rm -f "$next_env"
    log "FAIL menulis env sementara"
    exit 1
fi
chmod 600 "$next_env"

if ! docker compose --env-file "$next_env" -f "$COMPOSE" pull; then
    rm -f "$next_env"
    log "FAIL pull image $TAG"
    exit 1
fi

if ! "$BACKUP_SCRIPT"; then
    rm -f "$next_env"
    log "FAIL backup sebelum deploy"
    exit 1
fi

mv "$next_env" deploy.env
log "DEPLOY tag=$TAG"

if ! docker compose --env-file deploy.env -f "$COMPOSE" up -d --remove-orphans; then
    rollback
    log "FAIL compose up"
    exit 1
fi

for _ in $(seq 1 36); do
    if curl -fsS --max-time 5 "$HEALTH_URL" >/dev/null; then
        log "OK deploy tag=$TAG"
        exit 0
    fi
    sleep 5
done

rollback
log "FAIL health check setelah deploy tag=$TAG"
exit 1

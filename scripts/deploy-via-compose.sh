#!/usr/bin/env bash

set -euo pipefail

compose_files=(-f docker-compose.yml -f docker-compose.deploy.yml)

build_args=(
    --build-arg VITE_REVERB_APP_KEY="${VITE_REVERB_APP_KEY:-}"
    --build-arg VITE_REVERB_HOST="${VITE_REVERB_HOST:-}"
    --build-arg VITE_REVERB_PORT="${VITE_REVERB_PORT:-443}"
    --build-arg VITE_REVERB_SCHEME="${VITE_REVERB_SCHEME:-https}"
)

echo "Building images locally..."
docker compose "${compose_files[@]}" build "${build_args[@]}" --no-cache app web

echo "Running init tasks (migrate/cache)..."
docker compose "${compose_files[@]}" run --rm init init

echo "Starting updated services..."
docker compose "${compose_files[@]}" up -d app web queue scheduler reverb

echo "Deployment status:"
docker compose "${compose_files[@]}" ps

if [[ -n "${HEALTHCHECK_URL:-}" ]]; then
    echo "Waiting for healthcheck at ${HEALTHCHECK_URL}"
    for attempt in {1..20}; do
        if curl -fsS "${HEALTHCHECK_URL}" >/dev/null; then
            echo "Healthcheck passed"
            exit 0
        fi

        sleep 3
    done

    echo "Healthcheck failed after retries"
    exit 1
fi

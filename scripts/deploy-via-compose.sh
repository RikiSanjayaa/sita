#!/usr/bin/env bash

set -euo pipefail

if [[ -z "${APP_IMAGE:-}" ]]; then
    echo "APP_IMAGE is required"
    exit 1
fi

if [[ -z "${WEB_IMAGE:-}" ]]; then
    echo "WEB_IMAGE is required"
    exit 1
fi

compose_files=(-f docker-compose.yml -f docker-compose.deploy.yml)

echo "Pulling deployment images sequentially to prevent timeouts..."
docker pull "${APP_IMAGE}"
docker pull "${WEB_IMAGE}"

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

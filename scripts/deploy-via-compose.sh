#!/usr/bin/env bash

set -euo pipefail

compose_files=(-f docker-compose.yml -f docker-compose.deploy.yml)

echo "Pulling images..."
docker compose "${compose_files[@]}" pull app web init

echo "Checking the PHP security baseline before init..."
docker compose "${compose_files[@]}" run --rm --no-deps --entrypoint php app -r '
    if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4 || version_compare(PHP_VERSION, "8.4.27", "<")) {
        fwrite(STDERR, "Deployment requires patched PHP 8.4.27+ on the 8.4 branch; found ".PHP_VERSION.PHP_EOL);
        exit(1);
    }
'

echo "Running init tasks (migrate/cache)..."
docker compose "${compose_files[@]}" run --rm init init

echo "Starting updated services..."
docker compose "${compose_files[@]}" up -d --no-build app web queue scheduler reverb

echo "Deployment status:"
docker compose "${compose_files[@]}" ps

if [[ -n "${HEALTHCHECK_URL:-}" ]]; then
    echo "Waiting for healthcheck at ${HEALTHCHECK_URL}"
    healthcheck_passed=false
    for attempt in {1..20}; do
        if curl -fsS "${HEALTHCHECK_URL}" >/dev/null; then
            echo "Healthcheck passed"
            healthcheck_passed=true
            break
        fi

        sleep 3
    done

    if [[ "$healthcheck_passed" != "true" ]]; then
        echo "Healthcheck failed after retries"
        exit 1
    fi
fi

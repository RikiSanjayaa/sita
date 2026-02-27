#!/bin/sh

set -eu

wait_for_database() {
    attempts=0
    max_attempts=30

    until php -r '
        $driver = getenv("DB_CONNECTION") ?: "pgsql";
        $host = getenv("DB_HOST") ?: "db";
        $port = getenv("DB_PORT") ?: "5432";
        $database = getenv("DB_DATABASE") ?: "sita";
        $username = getenv("DB_USERNAME") ?: "sita";
        $password = getenv("DB_PASSWORD") ?: "sita_password";

        try {
            if ($driver === "mysql" || $driver === "mariadb") {
                $dsn = "mysql:host={$host};port={$port};dbname={$database}";
            } else {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            }

            new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => 2,
            ]);
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    '; do
        attempts=$((attempts + 1))

        if [ "$attempts" -ge "$max_attempts" ]; then
            echo "Database is not ready after ${max_attempts} attempts."
            exit 1
        fi

        sleep 2
    done
}

run_init() {
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY is required. Set APP_KEY in .env before running compose."
        exit 1
    fi

    wait_for_database

    php artisan migrate --force
    if [ "${RUN_DB_SEED:-false}" = "true" ]; then
        if [ "${SEED_IF_EMPTY_ONLY:-true}" = "true" ] && php -r '
            $driver = getenv("DB_CONNECTION") ?: "pgsql";
            $host = getenv("DB_HOST") ?: "db";
            $port = getenv("DB_PORT") ?: ($driver === "pgsql" ? "5432" : "3306");
            $database = getenv("DB_DATABASE") ?: "sita";
            $username = getenv("DB_USERNAME") ?: "sita";
            $password = getenv("DB_PASSWORD") ?: "sita_password";

            try {
                if ($driver === "mysql" || $driver === "mariadb") {
                    $dsn = "mysql:host={$host};port={$port};dbname={$database}";
                } else {
                    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                }

                $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_TIMEOUT => 2]);
                $stmt = $pdo->query("SELECT 1 FROM users LIMIT 1");
                $hasRows = $stmt && $stmt->fetchColumn();

                exit($hasRows ? 0 : 1);
            } catch (Throwable $e) {
                exit(1);
            }
        '; then
            echo "Skipping db:seed because users table already has data."
        else
            php artisan db:seed --force
        fi
    fi
    php artisan storage:link || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
}

run_queue() {
    exec php artisan queue:work --tries=3 --timeout=90 --sleep=2 --queue=default
}

run_scheduler() {
    exec php artisan schedule:work
}

run_reverb() {
    exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}"
}

mode="${1:-}"

if [ "$mode" = "init" ]; then
    run_init
    exit 0
fi

if [ "$mode" = "queue" ]; then
    run_queue
fi

if [ "$mode" = "scheduler" ]; then
    run_scheduler
fi

if [ "$mode" = "reverb" ]; then
    run_reverb
fi

exec "$@"

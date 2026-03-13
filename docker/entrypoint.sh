#!/usr/bin/env sh
set -eu

cd /app

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "${WAIT_FOR_DB:-1}" = "1" ]; then
    echo "Waiting for PostgreSQL at ${DB_HOST:-postgres}:${DB_PORT:-5432}..."
    until php -r '
        $host = getenv("DB_HOST") ?: "postgres";
        $port = getenv("DB_PORT") ?: "5432";
        $db = getenv("DB_DATABASE") ?: "barbershop_backend";
        $user = getenv("DB_USERNAME") ?: "root";
        $pass = getenv("DB_PASSWORD") ?: "";
        new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass);
    '; do
        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
fi

exec "$@"

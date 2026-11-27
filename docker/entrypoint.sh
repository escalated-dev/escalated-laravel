#!/bin/sh
set -eu

cd /host

if [ ! -f .env ] && [ -f .env.docker ]; then
    cp .env.docker .env
fi

# `php artisan serve` does not forward OS-only env vars to its worker, so an
# opt-in set through compose has to be written to .env to take effect.
if [ -n "${ESCALATED_API_ENABLED:-}" ] && [ -f .env ]; then
    sed -i '/^ESCALATED_API_ENABLED=/d' .env
    printf '\nESCALATED_API_ENABLED=%s\n' "${ESCALATED_API_ENABLED}" >> .env
fi

echo "[demo] waiting for postgres..."
until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-escalated}" >/dev/null 2>&1; do
    sleep 1
done

echo "[demo] resetting database schema"
php artisan migrate:fresh --force --no-interaction

echo "[demo] publishing package assets"
php artisan vendor:publish --tag=escalated-config --force
php artisan vendor:publish --tag=escalated-migrations --force
php artisan vendor:publish --tag=escalated-views --force

echo "[demo] running package + host migrations"
php artisan migrate --force --no-interaction

echo "[demo] seeding permissions + roles"
php artisan db:seed --force --no-interaction \
    --class='Escalated\Laravel\Database\Seeders\PermissionSeeder'

echo "[demo] seeding fixture data"
php artisan db:seed --force --no-interaction --class='Database\Seeders\DemoSeeder'

echo "[demo] ready — landing page: ${APP_URL:-http://localhost:8000}/demo"
echo "[demo] mailpit UI: http://localhost:${MAILPIT_PORT:-8025}"

exec "$@"

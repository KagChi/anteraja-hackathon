#!/bin/sh
set -e

# ==============================================================================
# Anteraja Frozen - Production Docker Entrypoint
# ==============================================================================

echo "=== Initializing Anteraja Backend (FrankenPHP / Octane) ==="

# 1. Pastikan direktori storage dan cache tersedia
mkdir -p /app/storage/framework/cache/data \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/storage/logs \
         /app/storage/app/private \
         /app/bootstrap/cache

chmod -R 775 /app/storage /app/bootstrap/cache || true

# 2. Tunggu database PostgreSQL siap
if [ -n "$DB_HOST" ]; then
    echo "Waiting for PostgreSQL at $DB_HOST:${DB_PORT:-5432}..."
    until php -r "
        \$host = getenv('DB_HOST');
        \$port = getenv('DB_PORT') ?: 5432;
        \$fp = @fsockopen(\$host, (int)\$port, \$errno, \$errstr, 2);
        if (\$fp) { fclose(\$fp); exit(0); } else { exit(1); }
    "; do
        echo "PostgreSQL is not ready yet - retrying in 2 seconds..."
        sleep 2
    done
    echo "PostgreSQL is reachable!"
fi

# 3. Jalankan migrasi database otomatis jika RUN_MIGRATIONS=true (default: true)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction
fi

# 4. Seed data referensi (FSM status guard & staff roles) jika RUN_SEEDS=true (default: true)
if [ "${RUN_SEEDS:-true}" = "true" ]; then
    echo "Seeding reference data (ReferenceDataSeeder)..."
    php artisan db:seed --class="Database\\Seeders\\ReferenceDataSeeder" --force --no-interaction || true
fi

# 5. Buat storage symlink
php artisan storage:link --force || true

# 6. Cache route, view, event untuk performa maksimal di environment production
if [ "${APP_ENV:-production}" = "production" ]; then
    echo "Optimizing Laravel for production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
fi

# 7. Jalankan Laravel Octane dengan FrankenPHP
OCTANE_PORT="${OCTANE_PORT:-80}"
OCTANE_WORKERS="${OCTANE_WORKERS:-4}"
OCTANE_MAX_REQUESTS="${OCTANE_MAX_REQUESTS:-500}"

echo "Starting FrankenPHP Octane on 0.0.0.0:${OCTANE_PORT} (workers: ${OCTANE_WORKERS}, max-requests: ${OCTANE_MAX_REQUESTS})..."
exec php artisan octane:frankenphp \
    --host="0.0.0.0" \
    --port="${OCTANE_PORT}" \
    --workers="${OCTANE_WORKERS}" \
    --max-requests="${OCTANE_MAX_REQUESTS}"

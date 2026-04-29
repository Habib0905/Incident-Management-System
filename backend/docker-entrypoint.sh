#!/bin/sh
set -e

echo "Running database migrations..."
php artisan migrate --force --no-interaction

echo "Seeding database..."
php artisan db:seed --force --no-interaction 2>/dev/null || echo "Seeding skipped or already complete."

echo "Starting application..."
exec "$@"

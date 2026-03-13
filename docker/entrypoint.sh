#!/bin/sh
set -e

# Warm Laravel's bootstrap caches now that APP_KEY and DB_* env vars are present
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run outstanding migrations (--force skips the production prompt)
php artisan migrate --force

# Hand off to Supervisor (runs Nginx + PHP-FPM)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

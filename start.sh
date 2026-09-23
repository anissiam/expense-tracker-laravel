#!/bin/sh
# Render entrypoint for the Laravel backend.
# - Binds to $PORT (Render injects it; defaults to 10000 for local runs)
# - Runs migrations, then serves the API
set -e

PORT="${PORT:-10000}"

echo "==> Laravel on Render: starting (port=${PORT}, APP_ENV=${APP_ENV:-unknown})"

if [ -z "$APP_KEY" ]; then
  echo "WARNING: APP_KEY is empty. Set it in the Render dashboard (php artisan key:generate --show)."
fi

# Ensure writable paths exist (ephemeral filesystem)
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

# Link public storage (harmless if already linked)
php artisan storage:link || true

# Fresh config for the current env (never bake config cache into the image)
php artisan config:clear

# Schema first — fail fast if the DB is unreachable so Render marks the deploy
php artisan migrate --force

# Optimize for production boot
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Serving on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"

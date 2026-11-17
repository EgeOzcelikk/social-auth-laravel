#!/bin/sh
# Prepares the app on every container start so `docker compose up` is the only command needed.
# Every step is idempotent: it only does work when something is missing.
set -e

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -d node_modules ]; then
    npm ci
fi

if [ ! -f public/build/manifest.json ]; then
    npm run build
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

php artisan migrate --force

# Makes uploaded profile photos (storage/app/public) reachable under /storage.
if [ ! -L public/storage ]; then
    php artisan storage:link
fi

exec "$@"

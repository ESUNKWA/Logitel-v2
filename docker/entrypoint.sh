#!/bin/sh
# Container start-up: check the configuration, wait for PostgreSQL, run the migrations,
# then hand over to FrankenPHP. See DOCKER.md.
set -e

# Single source for the database credentials: the POSTGRES_* variables shared with the db service.
export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_HOST="${DB_HOST:-db}"
export DB_PORT="${DB_PORT:-5432}"
export DB_DATABASE="${DB_DATABASE:-$POSTGRES_DB}"
export DB_USERNAME="${DB_USERNAME:-$POSTGRES_USER}"
export DB_PASSWORD="${DB_PASSWORD:-$POSTGRES_PASSWORD}"

if [ "$APP_ENV" = "production" ] && [ -z "$APP_KEY" ]; then
    echo "ERREUR : APP_KEY est vide dans .env.production. Générez-la avec :" >&2
    echo "  docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show" >&2
    exit 1
fi

echo "Attente de PostgreSQL ($DB_HOST:$DB_PORT)…"
tries=0
until php -r 'try { new PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }'; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
        echo "ERREUR : PostgreSQL injoignable après 2 minutes." >&2
        exit 1
    fi
    sleep 2
done

# Writable folders (they live in a volume) and the public link to the photos.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
ln -sfn ../storage/app/public public/storage

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
    # Empty database only: default manager account (credentials printed below, see DOCKER.md).
    php artisan hotel:ensure-admin --no-interaction || echo "ATTENTION : compte administrateur par défaut non créé (voir ci-dessus)." >&2
fi

if [ "$APP_ENV" = "production" ]; then
    php artisan optimize
else
    php artisan optimize:clear > /dev/null
fi

exec "$@"

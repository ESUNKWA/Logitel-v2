# syntax=docker/dockerfile:1
#
# Espace Katioka — image of the Laravel app served by FrankenPHP (Caddy + PHP in one process).
#   target "prod" (default): code and Composer dependencies baked in, no dev tools.
#   target "dev":            same runtime, the code is bind-mounted by compose.dev.yaml.
# See DOCKER.md.

FROM dunglas/frankenphp:1-php8.2-bookworm AS base

# PHP extensions used by the app: PostgreSQL, exact money maths (bcmath), photos (gd, exif), intl, zip.
RUN install-php-extensions pdo_pgsql pgsql bcmath intl exif gd zip opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/app.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

# Run as an unprivileged user (allowed to bind ports 80/443).
RUN useradd --uid 1000 --create-home appuser \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R appuser:appuser /data/caddy /config/caddy

WORKDIR /app
ENV SERVER_NAME=":80"

ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# ---------------------------------------------------------------- development
FROM base AS dev
ENV APP_ENV=local
USER appuser

# ---------------------------------------------------------------- production
FROM base AS prod
ENV APP_ENV=production \
    APP_DEBUG=false

COPY docker/php/opcache-prod.ini $PHP_INI_DIR/conf.d/zz-opcache.ini

# Dependencies first (cached layer), then the code.
COPY --chown=appuser:appuser composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY --chown=appuser:appuser . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && ln -sfn ../storage/app/public public/storage \
    && chown -R appuser:appuser storage bootstrap/cache

USER appuser

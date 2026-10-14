# syntax=docker/dockerfile:1

# Every stage stays on a Debian/glibc base: the Bun, PHP and FrankenPHP images
# all publish a musl (-alpine) variant, and this app is not built for musl.

# --- Frontend assets -------------------------------------------------------
FROM oven/bun:1-debian AS assets
WORKDIR /app
COPY package.json bun.lock ./
RUN bun install --frozen-lockfile
COPY vite.config.js tsconfig.json biome.json postcss.config.js ./
COPY resources ./resources
COPY public ./public
RUN bun run build

# --- Composer dependencies -------------------------------------------------
FROM php:8.5-cli-bookworm AS vendor

ARG COMPOSER_VERSION=2.10.3
ARG COMPOSER_SHA256=7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6

RUN apt-get update && apt-get install -y --no-install-recommends unzip git \
    && curl -fsSL "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" -o /usr/local/bin/composer \
    && echo "${COMPOSER_SHA256}  /usr/local/bin/composer" | sha256sum -c - \
    && chmod +x /usr/local/bin/composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY resources ./resources
COPY routes ./routes
COPY public ./public
COPY artisan composer.json composer.lock ./
RUN composer dump-autoload --no-dev --optimize --no-interaction --no-scripts

# --- Runtime: FrankenPHP (Caddy) in Octane worker mode ----------------------
FROM dunglas/frankenphp:1-php8.5-bookworm AS runtime

RUN install-php-extensions pdo_pgsql gd mbstring bcmath pcntl zip redis

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Setup entrypoint script
COPY deploy/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs storage/app/private bootstrap/cache \
    && php artisan package:discover --ansi

# Expose port 80 (sesuai upstream reverse proxy: server anteraja_nginx:80)
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

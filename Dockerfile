FROM dunglas/frankenphp:1-php8.5-bookworm AS php-base

RUN install-php-extensions \
    intl \
    opcache \
    pdo_pgsql \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM php-base AS app-build

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .

RUN rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi

FROM node:22-bookworm-slim AS frontend-build

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

RUN npm run build

FROM php-base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=database \
    SESSION_SECURE_COOKIE=true \
    CACHE_STORE=database \
    VIEW_COMPILED_PATH=/tmp/laravel/views \
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data

WORKDIR /app

COPY --from=app-build /app /app
COPY --from=frontend-build /app/public/build /app/public/build
COPY Caddyfile /etc/caddy/Caddyfile

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcap2-bin \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p /tmp/laravel/views /config/caddy /data/caddy \
    && chown -R www-data:www-data /tmp/laravel /config /data \
    && rm -f /usr/bin/composer \
    && apt-get purge -y --auto-remove libcap2-bin \
    && rm -rf /var/lib/apt/lists/*

USER www-data

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

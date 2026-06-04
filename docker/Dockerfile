# syntax=docker/dockerfile:1.7

# ---------- 1. Front-end assets ----------
FROM node:22-alpine AS assets

WORKDIR /host

# @escalated-dev/escalated declares @escalated-dev/locale as a github: dependency
# (github:escalated-dev/escalated-locale#vX.Y.Z), so npm needs git to resolve it.
RUN apk add --no-cache git

COPY docker/host-app/package.json docker/host-app/package-lock.json* ./
RUN npm install --no-audit --no-fund

COPY docker/host-app/resources ./resources
COPY docker/host-app/vite.config.js docker/host-app/tailwind.config.js docker/host-app/postcss.config.js ./
RUN npm run build

# ---------- 2. Composer deps ----------
FROM composer:2 AS vendor

COPY composer.json /package/composer.json
COPY src /package/src
COPY config /package/config
COPY database /package/database
COPY resources /package/resources
COPY routes /package/routes
COPY stubs /package/stubs

COPY docker/host-app/composer.json /host/composer.json
WORKDIR /host
RUN composer install \
    --no-interaction --no-progress --no-dev --prefer-dist \
    --optimize-autoloader --no-scripts

# ---------- 3. Runtime ----------
FROM php:8.3-cli-alpine AS runtime

RUN apk add --no-cache postgresql-client libpq icu-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        icu-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        intl \
        opcache \
    && apk del --no-network .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

WORKDIR /host

COPY --from=vendor /package /package
COPY --from=vendor /host /host
COPY docker/host-app/ /host/
COPY --from=assets /host/public/build /host/public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /host/storage/framework/cache/data /host/storage/framework/sessions /host/storage/framework/views /host/storage/logs /host/bootstrap/cache \
    && chmod -R 777 /host/storage /host/bootstrap/cache \
    && cd /host && php artisan package:discover --ansi

ENV APP_ENV=demo

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

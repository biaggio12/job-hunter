# syntax=docker/dockerfile:1
FROM composer:2 AS composer

# --- Obraz bazowy: PHP + rozszerzenia ---
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache icu-libs libpq libzip tzdata fcgi \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev \
 && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql opcache zip \
 && apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1

# --- Testy: zależności dev, `docker compose --profile test run --rm tests` ---
FROM base AS test
ENV APP_ENV=test
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction
COPY backend/ ./
RUN composer dump-autoload
CMD ["sh", "-c", "php bin/console doctrine:database:create --if-not-exists -q && php bin/console doctrine:migrations:migrate -n -q && php bin/phpunit"]

# --- Produkcja (API php-fpm i worker) ---
FROM base AS prod
ENV APP_ENV=prod APP_DEBUG=0

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

# Zależności osobno, żeby cache warstw działał przy zmianach kodu.
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY backend/ ./
RUN rm -rf var/cache var/log tests \
 && composer dump-autoload --no-dev --classmap-authoritative \
 && composer dump-env prod \
 && php bin/console cache:clear --no-warmup \
 && php bin/console cache:warmup \
 && chown -R www-data:www-data var

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM node:22-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
    --optimize-autoloader --no-scripts --ignore-platform-reqs

FROM php:8.4-cli-bookworm AS filament-assets

WORKDIR /app
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl mbstring zip pdo_mysql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    php artisan filament:assets

FROM nginx:1.27-alpine

COPY public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
COPY --from=filament-assets /app/public/css/filament /var/www/html/public/css/filament
COPY --from=filament-assets /app/public/js/filament /var/www/html/public/js/filament

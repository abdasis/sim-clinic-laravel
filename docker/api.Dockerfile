# API Dockerfile — Laravel 13 PHP-FPM (sim-clinic)
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    postgresql-client \
    libzip-dev \
    zip \
    unzip \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    autoconf \
    build-base \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip pcntl gd exif \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

# Copy composer files first for caching
COPY apps/api/composer.json apps/api/composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy rest of API
COPY apps/api/ .

# Ensure bootstrap/cache exists before artisan post-autoload scripts run
RUN mkdir -p /var/www/api/bootstrap/cache \
    && chown -R www-data:www-data /var/www/api/storage /var/www/api/bootstrap/cache \
    && chmod -R 775 /var/www/api/storage /var/www/api/bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction

EXPOSE 9000

CMD ["php-fpm", "-F"]

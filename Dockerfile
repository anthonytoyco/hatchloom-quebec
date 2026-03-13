# =============================================================================
# Stage 1 - Composer dependencies
# =============================================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# =============================================================================
# Stage 2 - Production image
# =============================================================================
FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

# System dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    && docker-php-ext-install pdo pdo_pgsql zip opcache

# PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

# Copy application source
COPY . .

# Copy pre-built vendor directory from Stage 1
COPY --from=vendor /app/vendor ./vendor

# Set correct ownership
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx and Supervisor configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint: bootstraps the app (cache, migrations) then hands off to Supervisor
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]

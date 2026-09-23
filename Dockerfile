# Production-ready image for Render (also works for local `docker build`).
# Render injects $PORT (default 10000) and forwards HTTP to it — the
# entrypoint script binds `php artisan serve` to 0.0.0.0:$PORT.
FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (pdo_pgsql is what Laravel uses)
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Leverage layer caching: install deps before copying the full source
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --prefer-dist --no-scripts --no-autoloader

# Copy application source
COPY . .

# Finish Composer install + optimize autoloader (runs package discovery)
RUN composer dump-autoload --optimize --no-dev \
 && composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

# Writable dirs for Laravel on Render's ephemeral filesystem
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Entrypoint: migrate + cache config + serve on $PORT
COPY start.sh /var/www/start.sh
RUN chmod +x /var/www/start.sh

# Render sets $PORT at runtime (defaults to 10000). Expose it for clarity.
EXPOSE 10000

CMD ["sh", "/var/www/start.sh"]

FROM php:8.2-fpm

WORKDIR /var/www/html

# Install system dependencies INCLUDING ImageMagick and nginx
RUN apt-get update && apt-get install -y \
    git \
    curl \
    nginx \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmagickwand-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && rm -f /etc/nginx/sites-enabled/default \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy app
COPY . /var/www/html

# nginx site template + entrypoint
COPY docker/nginx.conf.template /etc/nginx/templates/laravel.conf
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Permissions: storage + bootstrap/cache writable by the php-fpm worker (www-data) and
# by the boot-time artisan caches.
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# APP_KEY comes from the runtime environment (Railway vars); .env is NOT shipped.
RUN php artisan key:generate --no-interaction || true

EXPOSE 8080

# nginx (foreground, PID 1) + php-fpm (background). Migrations/caches run in start.sh.
# Runs as root so nginx/php-fpm masters can bind and drop to www-data workers.
CMD ["/usr/local/bin/start.sh"]

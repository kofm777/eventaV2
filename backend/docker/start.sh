#!/bin/sh
# Production container entrypoint: nginx + php-fpm (multi-worker, handles concurrency),
# replacing the single-threaded `php artisan serve`.
set -e

cd /var/www/html

# DB schema + idempotent seeders (safe to re-run every boot).
php artisan migrate --force --seed

# Framework caches. config:cache is safe here (no env() outside config/). view:cache is
# safe. route:cache is intentionally skipped — routes/web.php has a closure route.
php artisan config:cache || true
php artisan view:cache || true
php artisan storage:link || true

# Render the nginx listen port (Railway injects $PORT; the domain targetPort is 8080).
: "${PORT:=8080}"
sed "s/__PORT__/${PORT}/g" /etc/nginx/templates/laravel.conf > /etc/nginx/conf.d/default.conf

# php-fpm in the background, nginx in the foreground as PID 1.
php-fpm -D
exec nginx -g 'daemon off;'

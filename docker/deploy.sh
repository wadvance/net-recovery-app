#!/bin/bash
set -e

cd /var/www/html

echo "Caching config..."
php artisan config:cache || true

echo "Caching routes..."
php artisan route:cache || true

echo "Running migrations..."
php artisan migrate --force || true

echo "Creating storage link..."
php artisan storage:link || true

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx..."
exec nginx -g 'daemon off;'
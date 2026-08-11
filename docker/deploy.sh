#!/bin/bash
set -e

cd /var/www/html

echo "Running composer..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Caching config..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Running migrations..."
php artisan migrate --force

echo "Creating storage link..."
php artisan storage:link

echo "Starting services..."
# Start PHP-FPM
php-fpm -D

# Start nginx
nginx -g 'daemon off;'

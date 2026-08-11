#!/bin/bash
set -e

cd /var/www/html

# Generate app key if not set
php artisan key:generate --force

# Run migrations
php artisan migrate --force

# Cache config and routes
php artisan config:cache
php artisan route:cache

# Create storage link
php artisan storage:link

# Start PHP-FPM
php-fpm

# Start nginx in foreground
nginx -g 'daemon off;'

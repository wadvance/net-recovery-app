#!/bin/sh
set -e

cd /var/www/html

php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan storage:link

php-fpm -D
nginx -g 'daemon off;'

#!/bin/bash
set -e

cd /var/www/html

echo "Caching config..."
php artisan config:cache || true

echo "Caching routes..."
php artisan route:cache || true

echo "Preparing database for writable location if needed..."
if [ "${DB_DATABASE:-}" = "/tmp/database.sqlite" ]; then
  cp -f /var/www/html/database/database.sqlite /tmp/database.sqlite || true
fi

echo "Running migrations..."
php artisan migrate --force || true

echo "Creating storage link..."
php artisan storage:link || true

echo "Starting PHP-FPM..."
php-fpm -D

echo "Substituting PORT in nginx config..."
envsubst '${PORT}' < /etc/nginx/sites-enabled/default.conf > /etc/nginx/sites-enabled/default.conf.tmp
mv /etc/nginx/sites-enabled/default.conf.tmp /etc/nginx/sites-enabled/default.conf
nginx -t

echo "Starting Nginx..."
exec nginx -g 'daemon off;'
FROM php:8.3-cli
RUN apt-get update && apt-get install -y libpng-dev libonig-dev libxml2-dev libsqlite3-dev libjpeg-dev libfreetype6-dev libzip-dev zip unzip git curl && docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install zip gd && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && rm -rf /var/lib/apt/lists/*
RUN echo "upload_max_filesize=50M" >> /usr/local/etc/php/conf.d/uploads.ini && echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/uploads.ini && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/uploads.ini && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/uploads.ini
WORKDIR /app
COPY backend/ ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --optimize-autoloader --no-dev
RUN mkdir -p storage/app/imports storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache && chmod -R 777 storage bootstrap/cache && cp .env.example .env && php artisan key:generate --ansi && php artisan migrate --force --seed
EXPOSE 8000
CMD ["sh", "-c", "php artisan config:clear && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]

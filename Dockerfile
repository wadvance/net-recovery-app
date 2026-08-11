# Build frontend
FROM node:20-alpine AS frontend
WORKDIR /app/admin-panel
COPY admin-panel/package*.json ./
RUN npm ci
COPY admin-panel/ ./
RUN npm run build

# Production image
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    oniguruma \
    libxml2 \
    unzip \
    git \
    curl \
    build-base \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_sqlite \
    mbstring \
    bcmath \
    gd \
    zip \
    xml \
    fileinfo \
    tokenizer \
    && apk del build-base libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev libxml2-dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN rm -f /etc/nginx/http.d/default.conf
COPY docker/nginx.conf /etc/nginx/http.d/app.conf

WORKDIR /var/www/html

COPY backend ./
COPY --from=frontend /app/admin-panel/public/admin ./public/admin

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080
CMD ["/usr/local/bin/start.sh"]

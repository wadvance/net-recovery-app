# Stage 1: Build frontend
FROM node:20-bookworm AS frontend
WORKDIR /app
COPY admin-panel/package*.json ./
RUN npm ci
COPY admin-panel/ ./
RUN npm run build

# Stage 2: PHP 8.3 + Nginx
FROM php:8.3-fpm-bookworm

# Install system deps (including build tools for extensions)
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    unzip \
    curl \
    git \
    gettext-base \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_sqlite sqlite3 mbstring bcmath gd zip pcntl fileinfo tokenizer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Nginx config
COPY docker/nginx.conf /etc/nginx/sites-enabled/default.conf
RUN rm -f /etc/nginx/sites-enabled/default

# Deploy script
COPY docker/deploy.sh /usr/local/bin/deploy.sh
RUN chmod +x /usr/local/bin/deploy.sh

WORKDIR /var/www/html

# Backend code
COPY backend ./

# Frontend build
COPY --from=frontend /app/public/admin ./public/admin

# Install PHP deps
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080
CMD ["/usr/local/bin/deploy.sh"]
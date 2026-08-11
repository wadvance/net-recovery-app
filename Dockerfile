# Build stage for frontend
FROM node:20-alpine AS frontend
WORKDIR /app/admin-panel
COPY admin-panel/package*.json ./
RUN npm ci
COPY admin-panel/ ./
RUN npm run build

# Production stage
FROM php:8.3-fpm-bookworm

# Install dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    unzip \
    git \
    curl \
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
        tokenizer

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure nginx
RUN rm /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-available/app
RUN ln -s /etc/nginx/sites-available/app /etc/nginx/sites-enabled/app

# Set working directory
WORKDIR /var/www/html

# Copy backend
COPY backend ./

# Copy frontend build
COPY --from=frontend /app/admin-panel/public/admin ./public/admin

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Generate app key and run migrations on start
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]

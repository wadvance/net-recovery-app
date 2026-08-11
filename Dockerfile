FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y \
    nginx \
    unzip \
    curl \
    git \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo pdo_sqlite mbstring bcmath gd zip xml fileinfo tokenizer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-available/app
RUN ln -s /etc/nginx/sites-available/app /etc/nginx/sites-enabled/app

WORKDIR /var/www/html

COPY backend ./
COPY admin-panel ./admin-panel

RUN cd admin-panel && npm ci && npm run build && mv public/admin /var/www/html/public/admin && cd .. && rm -rf admin-panel

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/start.sh /usr/bin/start.sh
RUN chmod +x /usr/bin/start.sh

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080
CMD ["/usr/bin/start.sh"]

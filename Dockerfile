FROM php:8.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y nginx unzip curl git \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_sqlite mbstring bcmath zip xml fileinfo tokenizer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-available/app
RUN ln -s /etc/nginx/sites-available/app /etc/nginx/sites-enabled/app

WORKDIR /var/www/html

COPY backend ./
COPY admin-panel ./admin-panel

RUN cd admin-panel && npm ci && npm run build && mv public/admin /var/www/html/public/admin && cd .. && rm -rf admin-panel

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080
CMD ["/usr/local/bin/start.sh"]

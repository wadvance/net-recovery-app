FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev \
    libxml2-dev libsqlite3-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-dev

COPY backend/ .

RUN cp .env.example .env && \
    php artisan config:clear && \
    php artisan key:generate --ansi && \
    php artisan migrate --force

EXPOSE 8000

CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]


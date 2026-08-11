# Build frontend
FROM node:20-alpine AS frontend
WORKDIR /app/admin-panel
COPY admin-panel/package*.json ./
RUN npm ci
COPY admin-panel/ ./
RUN npm run build

# Production image based on nginx-php-fpm
FROM richarvey/nginx-php-fpm:1.11.2

# Copy nginx config
COPY docker/nginx.conf /etc/nginx/sites-available/default.conf

# Copy deploy script
COPY docker/deploy.sh /var/www/html/deploy.sh
RUN chmod +x /var/www/html/deploy.sh

# Copy backend
COPY backend /var/www/html

# Copy frontend build
COPY --from=frontend /app/admin-panel/public/admin /var/www/html/public/admin

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

ENV WEBROOT /var/www/html/public
ENV PHP_ENV production
ENV PHP_OPCACHE_REVALIDATE_FREQ 0

EXPOSE 8080

CMD ["/bin/bash", "/var/www/html/deploy.sh"]

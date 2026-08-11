#!/bin/bash
set -e

# ============================================================
# NET RECOVERY - Instalación en VPS Ubuntu 24.04
# Uso: bash deploy-ubuntu.sh <tu_dominio_o_ip> <tu_email>
# ============================================================

DOMAIN="${1:-localhost}"
EMAIL="${2:-}"
APP_DIR="/var/www/net-recovery"
REPO_URL="https://github.com/wadvance/net-recovery-app.git"

echo "=========================================="
echo "  Instalando NET RECOVERY en Ubuntu"
echo "  Dominio/IP: $DOMAIN"
echo "=========================================="

# ---------- 1. Actualizar sistema ----------
echo ">>> Actualizando sistema..."

export DEBIAN_FRONTEND=noninteractive

sudo apt-get update -y

# ---------- 2. Instalar software base ----------
echo ">>> Instalando paquetes base..."

sudo apt-get install -y software-properties-common curl git unzip nginx \
    ca-certificates lsb-release gnupg

# ---------- 3. Instalar PHP 8.3 ----------
echo ">>> Instalando PHP 8.3..."

sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update -y
sudo apt-get install -y php8.3-fpm php8.3-cli php8.3-common php8.3-mysql \
    php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd \
    php8.3-bcmath php8.3-curl php8.3-intl php8.3-tokenizer php8.3-fileinfo

# ---------- 4. Instalar Composer ----------
echo ">>> Instalando Composer..."

php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('/tmp/composer-setup.php');"

# ---------- 5. Instalar Node.js 20 ----------
echo ">>> Instalando Node.js 20..."

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# ---------- 6. Clonar repositorio ----------
echo ">>> Clonando repositorio..."

sudo mkdir -p /var/www
if [ ! -d "$APP_DIR" ]; then
    sudo git clone "$REPO_URL" "$APP_DIR"
else
    cd "$APP_DIR"
    sudo git pull
fi

# ---------- 7. Configurar backend ----------
echo ">>> Configurando backend..."

cd "$APP_DIR/backend"
sudo cp .env.example .env
sudo sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
sudo sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
sudo sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
sudo sed -i "s|APP_NAME=.*|APP_NAME=\"Net Recovery\"|" .env
sudo sed -i "s|DB_CONNECTION=sqlite|DB_CONNECTION=sqlite|" .env

# SQLite database
sudo touch database/database.sqlite
sudo chown -R www-data:www-data database
sudo chmod -R 775 storage bootstrap/cache

# Instalar dependencias PHP
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction || composer install --no-dev --optimize-autoloader --no-interaction

# Key
sudo php artisan key:generate --force || php artisan key:generate --force

sudo php artisan migrate --force || php artisan migrate --force
sudo php artisan db:seed --force || php artisan db:seed --force || true
sudo php artisan storage:link || php artisan storage:link || true
sudo php artisan config:cache || true
sudo php artisan route:cache || true

# ---------- 8. Construir frontend ----------
echo ">>> Construyendo admin panel..."

cd "$APP_DIR/admin-panel"
sudo npm ci
sudo npm run build

# Copiar build a public/admin de Laravel
sudo mkdir -p "$APP_DIR/backend/public/admin"
sudo cp -r public/admin/* "$APP_DIR/backend/public/admin/"
sudo chown -R www-data:www-data "$APP_DIR/backend/public"

# ---------- 9. Permisos ----------
echo ">>> Configurando permisos..."

sudo chown -R www-data:www-data "$APP_DIR"
sudo chmod -R 775 "$APP_DIR/backend/storage" "$APP_DIR/backend/bootstrap/cache"

# ---------- 10. Configurar Nginx ----------
echo ">>> Configurando Nginx..."

sudo tee /etc/nginx/sites-available/net-recovery > /dev/null << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name DOMAIN_PLACEHOLDER;

    root /var/www/net-recovery/backend/public;
    index index.php;

    client_max_body_size 50M;

    # Admin panel
    location /admin/ {
        alias /var/www/net-recovery/backend/public/admin/;
        try_files $uri $uri/ /admin/index.html;
    }

    # PHP
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Main
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Hidden files
    location ~ /\. {
        deny all;
    }

    location ~ \.well-known/acme-challenge {
        allow all;
    }
}
EOF

# Reemplazar dominio
sudo sed -i "s|DOMAIN_PLACEHOLDER|$DOMAIN|" /etc/nginx/sites-available/net-recovery

sudo ln -sf /etc/nginx/sites-available/net-recovery /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm

# ---------- 11. Firewall (si existe) ----------
sudo ufw allow 'Nginx Full' 2>/dev/null || true
sudo ufw allow OpenSSH 2>/dev/null || true

# ---------- 12. Certificado SSL (opcional) ----------
if [ "$DOMAIN" != "localhost" ] && [ -n "$EMAIL" ] && command -v certbot >/dev/null 2>&1; then
    echo ">>> Instalando certificado SSL..."
    sudo certbot --nginx -d "$DOMAIN" -m "$EMAIL" --agree-tos --non-interactive --redirect
fi

echo ""
echo "=========================================="
echo "  INSTALACIÓN COMPLETADA"
echo "=========================================="
echo ""
echo "  Admin Panel: https://$DOMAIN/admin"
echo "  API:         https://$DOMAIN/api/v1/health"
echo ""
echo "  Credenciales:"
echo "    admin@recovery.local / password123"
echo "    supervisor@recovery.local / password123"
echo "=========================================="
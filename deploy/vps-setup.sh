#!/usr/bin/env bash
# Instala PHP 8.3 + Nginx + Composer en AlmaLinux/Rocky/CentOS para NET RECOVERY
# Ejecutar como root: bash vps-setup.sh
set -euo pipefail

echo "==> Actualizando sistema..."
dnf update -y

echo "==> Instalando EPEL y Remi (para PHP 8.3)..."
dnf install -y epel-release
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E %rhel).rpm

echo "==> Habilitando PHP 8.3..."
dnf module reset php -y
dnf module enable php:remi-8.3 -y

echo "==> Instalando PHP 8.3 con extensiones necesarias..."
dnf install -y \
  php php-cli php-fpm php-mbstring php-xml php-curl php-zip php-gd php-bcmath \
  php-pdo php-sqlite3 php-fileinfo php-tokenizer php-opcache php-intl \
  nginx git unzip

echo "==> Instalando Composer..."
if [ ! -f /usr/local/bin/composer ]; then
  php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

echo "==> Configurando PHP-FPM (usuario nginx, socket unix)..."
sed -i 's/^user = .*/user = nginx/' /etc/php-fpm.d/www.conf
sed -i 's/^group = .*/group = nginx/' /etc/php-fpm.d/www.conf

echo "==> Preparando directorio de la app..."
mkdir -p /var/www/net-recovery-app
chown -R nginx:nginx /var/www/net-recovery-app

echo "==> Habilitando servicios..."
systemctl enable php-fpm nginx
systemctl start php-fpm nginx

echo "==> Firewall (puertos web)..."
firewall-cmd --permanent --add-service=http 2>/dev/null || true
firewall-cmd --permanent --add-service=https 2>/dev/null || true
firewall-cmd --reload 2>/dev/null || true

echo "==> Versiones instaladas:"
php -v | head -1
composer --version
nginx -v 2>&1

cat <<'EOF'

Listo. Siguientes pasos:
1) Copia el proyecto a /var/www/net-recovery-app (backend + public/ en la raiz):
   cd /var/www/net-recovery-app
   (sube tu codigo: git clone, rsync o FTP, e instala deps)
2) Configura .env (APP_KEY, DB=sqlite, APP_URL=tu-dominio)
3) Crea el vhost Nginx usando deploy/nginx-net-recovery.conf
4) php artisan migrate --force && php artisan storage:link
EOF
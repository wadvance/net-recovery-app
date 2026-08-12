#!/usr/bin/env bash
# Despliega la API NET RECOVERY (Laravel + SQLite) en el VPS
# Ejecutar como root: bash vps-deploy-app.sh [tu-dominio-api]
# Ejemplo: bash vps-deploy-app.sh api.netrecovery.com
set -euo pipefail

APP_DIR="/var/www/net-recovery-app"
SERVER_NAME="${1:-localhost}"
FRONTEND_URL="${FRONTEND_URL:-https://net-recovery-app.web.app}"

if [ ! -d "${APP_DIR}" ]; then
  echo "==> Creando ${APP_DIR}"
  mkdir -p "${APP_DIR}"
fi
chown -R nginx:nginx "${APP_DIR}"

echo "==> Clonando/actualizando codigo desde GitHub..."
if [ ! -d "${APP_DIR}/.git" ]; then
  sudo -u nginx git clone https://github.com/wadvance/net-recovery-app.git "${APP_DIR}"
else
  cd "${APP_DIR}" && sudo -u nginx git fetch origin && sudo -u nginx git reset --hard origin/main
fi
cd "${APP_DIR}"

echo "==> Instalando dependencias de Composer..."
sudo -u nginx composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --working-dir=backend

echo "==> Configurando .env..."
if [ ! -f backend/.env ]; then
  cp backend/.env.example backend/.env
fi
cat > backend/.env <<EOF
APP_NAME="NET RECOVERY"
APP_ENV=production
APP_KEY=base64:Gya0/U7CTc8D9jwKA536jyaclCn0xJJXMJuF/8GCjOw=
APP_DEBUG=false
APP_URL=http://${SERVER_NAME}
APP_LOCALE=es
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/net-recovery-app/backend/database/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=sync

FRONTEND_URL=${FRONTEND_URL}

ZAVU_API_KEY=zv_live_3892d8aec663831de302a709b2841ca184871cb9e22e434a
ZAVU_BASE_URL=https://api.zavu.dev
ZAVU_SENDER=kd7eyphnd8t74e2mf9g2jqw2th8c7khm
ZAVU_TEMPLATE_ID=ks71hmj5vr9b0a68r5vs3k92y18c6smf

WHATSAPP_VERSION=v21.0
WHATSAPP_BASE_URL=https://graph.facebook.com
EOF
chown nginx:nginx backend/.env

echo "==> Permisos de storage y cache..."
sudo -u nginx mkdir -p backend/storage/framework/cache/data backend/storage/framework/sessions backend/storage/framework/views
sudo -u nginx chmod -R 775 backend/storage backend/bootstrap/cache

echo "==> Configuracion y migracion de la BD..."
cd backend
sudo -u nginx php artisan config:cache || true
sudo -u nginx php artisan migrate --force || true
sudo -u nginx php artisan storage:link || true
cd ..

echo "==> Creando vhost Nginx..."
sed -e "s|api.tu-dominio.com|${SERVER_NAME}|g" \
    deploy/nginx-net-recovery.conf > /etc/nginx/conf.d/net-recovery.conf
nginx -t && systemctl reload nginx

echo "==> Listo. API disponible en http://${SERVER_NAME}"
echo "    Frontend en Firebase Hosting (net-recovery-app.web.app)"
echo "    CORS permite: ${FRONTEND_URL}"
echo
echo "Recordatorio: apunta el DNS del dominio '${SERVER_NAME}' a la IP de este VPS."
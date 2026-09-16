#!/bin/bash
set -e
echo "=== NET RECOVERY - Oracle Cloud Deployment ==="
sudo apt-get update
sudo apt-get install -y apache2 php php-cli php-sqlite3 php-mbstring php-xml php-gd php-curl php-zip unzip git curl
sudo a2enmod rewrite headers
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
sudo git clone https://github.com/wadvance/net-recovery-app.git /var/www/net-recovery
sudo chown -R www-data:www-data /var/www/net-recovery
cd /var/www/net-recovery/backend
cp .env.example .env
APP_KEY=$(php artisan key:generate --show)
sudo sed -i "s/^APP_KEY=.*/APP_KEY=$APP_KEY/" .env
sudo sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sudo sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
sudo sed -i 's|APP_URL=http://localhost|APP_URL=https://YOUR_DOMAIN|' .env
sudo sed -i 's/YCLOUD_API_KEY=.*/YCLOUD_API_KEY=dd1e0d4689ca41394d4f3ce4e97012bc/' .env
sudo sed -i 's/YCLOUD_PHONE_NUMBER_ID=.*/YCLOUD_PHONE_NUMBER_ID=1324615497397442/' .env
sudo sed -i 's/YCLOUD_WABA_ID=.*/YCLOUD_WABA_ID=1770463167601759/' .env
sudo sed -i 's/YCLOUD_VERSION=v1/YCLOUD_VERSION=v2/' .env
composer install --no-dev --optimize-autoloader --no-interaction
sudo chmod -R 775 storage bootstrap/cache
php artisan migrate --force
sudo tee /etc/apache2/sites-available/net-recovery.conf > /dev/null << EOFA
<VirtualHost *:80>
    ServerName YOUR_DOMAIN
    DocumentRoot /var/www/net-recovery/backend/public
    <Directory /var/www/net-recovery/backend/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOFA
sudo a2ensite net-recovery.conf
sudo a2dissite 000-default.conf
sudo systemctl restart apache2
echo "=== Done! Visit https://YOUR_DOMAIN ==="

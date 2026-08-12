# Genera el paquete de despliegue para cPanel (hosting compartido sin SSH)
# Estructura: public_html/ (contenido de backend/public) + public_html/recovery/ (backend completo)
param(
  [string]$AppDir = "C:\PROYECTOS\NET RECOVERY - APP\recovery-app",
  [string]$OutDir = "C:\PROYECTOS\NET RECOVERY - APP\recovery-app\deploy\cpanel"
)

$ErrorActionPreference = "Stop"
$domain = "netrecovery.co4.in"
$frontendUrl = "https://netrecovery-app.web.app"

$backend = Join-Path $AppDir "backend"
$stage = Join-Path $OutDir "stage"
$recovery = Join-Path $stage "recovery"
$publicRoot = $stage

# Limpiar y preparar
if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Path $publicRoot -Force | Out-Null

Write-Host "==> Copiando backend a recovery/..."
Copy-Item -Path $backend -Destination $recovery -Recurse

# Eliminar artefactos innecesarios
foreach ($drop in @(
  "$recovery\public", "$recovery\vendor\bin",
  "$recovery\.env", "$recovery\.env.example"
)) {
  if (Test-Path $drop) { Remove-Item $drop -Recurse -Force }
}

Write-Host "==> Moviendo contenido de public/ a public_html/..."
Copy-Item -Path (Join-Path $backend "public\*") -Destination $publicRoot -Recurse

# El frontend admin/ se sirve desde Firebase; excluirlo del paquete
if (Test-Path (Join-Path $publicRoot "admin")) {
  Remove-Item (Join-Path $publicRoot "admin") -Recurse -Force
}

# index.php ajustado (public_html/index.php apunta a recovery/)
$indexPhp = @"
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists(`$maintenance = __DIR__.'/recovery/storage/framework/maintenance.php')) {
    require `$maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/recovery/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application `$app */
`$app = require_once __DIR__.'/recovery/bootstrap/app.php';

`$app->handleRequest(Request::capture());
"@
Set-Content -Path (Join-Path $publicRoot "index.php") -Value $indexPhp -Encoding UTF8

# .htaccess en public_html (de public/)
Copy-Item -Path (Join-Path $backend "public\.htaccess") -Destination $publicRoot -Force

# .env de produccion
$envFile = @"
APP_NAME="NET RECOVERY"
APP_ENV=production
APP_KEY=base64:Gya0/U7CTc8D9jwKA536jyaclCn0xJJXMJuF/8GCjOw=
APP_DEBUG=false
APP_URL=https://${domain}
APP_LOCALE=es
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=sync

FRONTEND_URL=${frontendUrl}

ZAVU_API_KEY=zv_live_3892d8aec663831de302a709b2841ca184871cb9e22e434a
ZAVU_BASE_URL=https://api.zavu.dev
ZAVU_SENDER=kd7eyphnd8t74e2mf9g2jqw2th8c7khm
ZAVU_TEMPLATE_ID=ks71hmj5vr9b0a68r5vs3k92y18c6smf

WHATSAPP_VERSION=v21.0
WHATSAPP_BASE_URL=https://graph.facebook.com
"@
Set-Content -Path (Join-Path $recovery ".env") -Value $envFile -Encoding ASCII

# Almacenamiento: creamos directorios framework y limpiamos cache
$storage = Join-Path $recovery "storage"
New-Item -ItemType Directory -Path "$storage\framework\cache\data" -Force | Out-Null
New-Item -ItemType Directory -Path "$storage\framework\sessions" -Force | Out-Null
New-Item -ItemType Directory -Path "$storage\framework\views" -Force | Out-Null
Get-ChildItem "$storage\framework\cache" -File -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem "$storage\framework\views" -File -ErrorAction SilentlyContinue | Remove-Item -Force
if (Test-Path "$storage\logs") { Remove-Item "$storage\logs" -Recurse -Force }

# Zip
$zip = Join-Path $OutDir "cpanel-deploy.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $stage "*") -DestinationPath $zip -CompressionLevel Optimal

Write-Host ""
Write-Host "==> LISTO: $zip"
Write-Host ""
Write-Host "Contenido del zip (a subir por File Manager al cPanel):"
Write-Host "  public_html/index.php        (entrada Laravel)"
Write-Host "  public_html/.htaccess"
Write-Host "  public_html/recovery/        (backend completo con vendor y BD)"
Write-Host ""
Write-Host "Instrucciones:"
Write-Host "  1. En cPanel -> File Manager -> public_html, sube y EXTRAE este zip"
Write-Host "  2. El resultado debe quedar: public_html/index.php y public_html/recovery/"
Write-Host "  3. En MultiPHP/Select PHP Version: elige PHP 8.2 o 8.3 para el dominio ${domain}"
Write-Host "  4. Listo: la API queda en https://${domain}/api/v1/health"

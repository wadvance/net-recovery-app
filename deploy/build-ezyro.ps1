# Build package for ezyro/iFastNet (webroot = htdocs, dominio netrecovery.unaux.com)
$ErrorActionPreference = "Stop"

$root = "C:\PROYECTOS\NET RECOVERY - APP\recovery-app"
$backend = Join-Path $root "backend"
$staging = Join-Path $root "deploy\ezyro\htdocs"
$domain = "netrecovery.unaux.com"

# Clean staging
if (Test-Path $staging) { Remove-Item -Recurse -Force $staging }
New-Item -ItemType Directory -Force -Path $staging | Out-Null

# 1. Copy backend (excluding heavy/unneeded)
robocopy $backend $staging /E /XD node_modules .git vendor tests .phpunit.cache /XF .env.example .phpunit.result.cache > $null

# 2. Copy vendor (needed - already installed locally)
robocopy (Join-Path $backend "vendor") (Join-Path $staging "vendor") /E /XD .git > $null

# 3. Copy database.sqlite (has data)
Copy-Item (Join-Path $backend "database\database.sqlite") (Join-Path $staging "database\database.sqlite") -Force

# 4. Move contents of public/ to root of htdocs
robocopy (Join-Path $backend "public") $staging /E /XD storage > $null

# 5. Write .env (production config for this domain)
$envContent = @"
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

ZAVU_API_KEY=zv_live_3892d8aec663831de302a709b2841ca184871cb9e22e434a
ZAVU_BASE_URL=https://api.zavu.dev
ZAVU_SENDER=kd7eyphnd8t74e2mf9g2jqw2th8c7khm
ZAVU_TEMPLATE_ID=ks71hmj5vr9b0a68r5vs3k92y18c6smf

WHATSAPP_VERSION=v21.0
WHATSAPP_BASE_URL=https://graph.facebook.com
"@
Set-Content -Path (Join-Path $staging ".env") -Value $envContent -Encoding ascii

# 6. Ensure writable dirs
$writable = @("storage\framework\views", "storage\framework\sessions", "storage\framework\cache\data", "storage\logs", "storage\app\public", "storage\app\private", "bootstrap\cache", "database")
foreach ($d in $writable) {
    $p = Join-Path $staging $d
    New-Item -ItemType Directory -Force -Path $p | Out-Null
}

# 7. Rewrite index.php paths for htdocs root layout
$index = Join-Path $staging "index.php"
$content = Get-Content $index -Raw
$content = $content.Replace("__DIR__.'/../vendor/autoload.php'", "__DIR__.'/vendor/autoload.php'")
$content = $content.Replace("__DIR__.'/../bootstrap/app.php'", "__DIR__.'/bootstrap/app.php'")
$content = $content.Replace("__DIR__.'/../storage", "__DIR__.'/storage")
Set-Content -Path $index -Value $content -Encoding utf8

# 8. Create root .htaccess with Laravel routing + storage rewrite
$htaccess = @'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    RewriteEngine On

    # Serve /storage/* from storage/app/public
    RewriteCond %{REQUEST_URI} ^/storage/
    RewriteRule ^storage/(.*)$ storage/app/public/$1 [L]

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Protect sensitive files
<FilesMatch "^(\.env|composer\.json|composer\.lock|artisan)$">
    Require all denied
</FilesMatch>

Options -Indexes
'@
Set-Content -Path (Join-Path $staging ".htaccess") -Value $htaccess -Encoding utf8

# 9. Protect storage internals but allow app/public
New-Item -ItemType Directory -Force -Path (Join-Path $staging "storage\app\public") | Out-Null
$storageHt = "RewriteEngine On`nRewriteRule ^app/public - [L]`nRewriteRule .* - [R=403,L]"
Set-Content -Path (Join-Path $staging "storage\.htaccess") -Value $storageHt -Encoding ascii

# 10. Zip it
$zip = Join-Path $root "deploy\ezyro\ezyro-htdocs.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $staging "*") -DestinationPath $zip -CompressionLevel Optimal

# Summary
$size = (Get-ChildItem $staging -Recurse -File | Measure-Object -Property Length -Sum).Sum / 1MB
Write-Host "Package ready at: $staging"
Write-Host "Zip: $zip"
Write-Host ("Size: {0:N1} MB" -f $size)

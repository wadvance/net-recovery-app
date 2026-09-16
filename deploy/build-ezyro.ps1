# Build package for ezyro/iFastNet (webroot = htdocs, dominio netrecovery.unaux.com)
$ErrorActionPreference = "Stop"

$root = "C:\PROYECTOS\NET RECOVERY - APP\recovery-app"
$backend = Join-Path $root "backend"
$staging = Join-Path $root "deploy\ezyro\htdocs"
$domain = "netrecovery.unaux.com"

# Load secrets from .env.secrets (not committed to git)
$secretsFile = Join-Path $PSScriptRoot "ezyro\.env.secrets"
if (-not (Test-Path $secretsFile)) {
    Write-Error "Secrets file not found: $secretsFile`nCopy .env.secrets.example to .env.secrets and fill in the values."
    exit 1
}
$secrets = @{}
Get-Content $secretsFile | ForEach-Object {
    if ($_ -match '^([^#=]+)=(.*)$') {
        $secrets[$matches[1].Trim()] = $matches[2].Trim()
    }
}

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
APP_KEY=$($secrets['APP_KEY'])
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

FRONTEND_URL=https://net-recovery-app.web.app

ZAVU_API_KEY=$($secrets['ZAVU_API_KEY'])
ZAVU_BASE_URL=$($secrets['ZAVU_BASE_URL'])
ZAVU_SENDER=$($secrets['ZAVU_SENDER'])
ZAVU_TEMPLATE_ID=$($secrets['ZAVU_TEMPLATE_ID'])

WHATSAPP_VERSION=$($secrets['WHATSAPP_VERSION'])
WHATSAPP_BASE_URL=$($secrets['WHATSAPP_BASE_URL'])
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

# 8. Create root .htaccess from the improved template (SPA rewrite + no-cache)
Copy-Item (Join-Path $PSScriptRoot "ezyro\htaccess-root") (Join-Path $staging ".htaccess") -Force

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

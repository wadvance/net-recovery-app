# Build package for InfinityFree (webroot = htdocs)
$ErrorActionPreference = "Stop"

$root = "C:\PROYECTOS\NET RECOVERY - APP\recovery-app"
$backend = Join-Path $root "backend"
$staging = Join-Path $root "deploy\infinityfree\htdocs"

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

# 5. Copy .env (production config)
Copy-Item (Join-Path $backend ".env") (Join-Path $staging ".env") -Force

# 6. Ensure writable dirs
$writable = @("storage\framework\views", "storage\framework\sessions", "storage\framework\cache\data", "storage\logs", "storage\app\public", "storage\app\private", "bootstrap\cache", "database")
foreach ($d in $writable) {
    $p = Join-Path $staging $d
    New-Item -ItemType Directory -Force -Path $p | Out-Null
}
& "C:\php83\php.exe" -r @'
$d = $argv[1];
'r'; 
'@ $staging 2>&1 | Out-Null

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

# Summary
Write-Host "Package ready at: $staging"
Write-Host "Size: " ((Get-ChildItem $staging -Recurse -File | Measure-Object -Property Length -Sum).Sum / 1MB) "MB"
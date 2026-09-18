# Railway Deployment Script for NET RECOVERY
# Run this script after authenticating with `railway login`

param(
    [string]$Domain = "https://netrecovery.railway.app"
)

$ErrorActionPreference = "Stop"

Write-Host "=== NET RECOVERY - Railway Deployment ===" -ForegroundColor Green
Write-Host ""

# Step 1: Login if not authenticated
Write-Host "[1/5] Checking authentication..." -ForegroundColor Cyan
try {
    railway whoami 2>$null
    Write-Host "  Already authenticated" -ForegroundColor Green
} catch {
    Write-Host "  Not authenticated. Running railway login..." -ForegroundColor Yellow
    railway login --browserless
}

# Step 2: Initialize project
Write-Host "[2/5] Initializing project..." -ForegroundColor Cyan
railway init -n "net-recovery-backend" -y 2>&1

# Step 3: Add service with Dockerfile
Write-Host "[3/5] Adding service..." -ForegroundColor Cyan
railway up -y -d --path . 2>&1

# Step 4: Set environment variables
Write-Host "[4/5] Setting environment variables..." -ForegroundColor Cyan
$envVars = @{
    "APP_KEY" = "base64:" + [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes((random -Maximum 9999999).ToString() + "secret"))
    "APP_ENV" = "production"
    "APP_DEBUG" = "false"
    "APP_URL" = $Domain
    "DB_CONNECTION" = "sqlite"
    "YCLOUD_API_KEY" = "dd1e0d4689ca41394d4f3ce4e97012bc"
    "YCLOUD_PHONE_NUMBER" = "+50760832368"
    "YCLOUD_PHONE_NUMBER_ID" = "1324615497397442"
    "YCLOUD_WABA_ID" = "1770463167601759"
    "YCLOUD_API_URL" = "https://api.ycloud.com"
    "YCLOUD_VERSION" = "v2"
    "SESSION_DRIVER" = "database"
    "CACHE_STORE" = "database"
    "QUEUE_CONNECTION" = "database"
}

foreach ($kv in $envVars.GetEnumerator()) {
    Write-Host "  Setting $($kv.Key)..." -ForegroundColor Gray
    railway variable set $kv.Key=$kv.Value 2>&1 | Out-Null
}

# Step 5: Run migrations
Write-Host "[5/5] Running migrations..." -ForegroundColor Cyan
railway run "php artisan migrate --force" 2>&1

Write-Host ""
Write-Host "=== Deployment Complete! ===" -ForegroundColor Green
Write-Host "Your app is at: $Domain"
Write-Host ""
Write-Host "Next steps:"
Write-Host "  1. Update admin panel .env.production:"
Write-Host "     VITE_API_BASE_URL=$Domain/api/v1"
Write-Host "  2. Re-deploy admin panel to Firebase"
Write-Host "  3. Update Cloudflare Worker ORIGIN to the Railway URL"
Write-Host ""
Write-Host "To update the worker, edit deploy/webhook-relay/worker.js:"
Write-Host "  Change ORIGIN = 'https://netrecovery.unaux.com'"
Write-Host "  To ORIGIN = '$Domain'"
Write-Host "  Then run: npx wrangler deploy"

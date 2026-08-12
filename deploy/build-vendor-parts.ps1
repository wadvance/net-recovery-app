$ErrorActionPreference = "Stop"
cd "C:\PROYECTOS\NET RECOVERY - APP\recovery-app\backend"
$outDir = "C:\Users\ardis\AppData\Local\Temp\opencode\parts"
if (Test-Path $outDir) { Get-ChildItem $outDir -Filter "v-*.zip" | Remove-Item -Force -ErrorAction SilentlyContinue }

$topDirs = @(Get-ChildItem "vendor" -Directory | Sort-Object Name)

# Big packages -> separate zips
$big = @{
    "composer"            = "vendor\composer"
    "dompdf"              = "vendor\dompdf"
    "ezyang"              = "vendor\ezyang"
    "guzzlehttp"          = "vendor\guzzlehttp"
    "laravel-framework"   = "vendor\laravel\framework"
    "league"              = "vendor\league"
    "nesbot"              = "vendor\nesbot"
    "phpoffice"           = "vendor\phpoffice"
    "psy"                 = "vendor\psy"
    "symfony"             = "vendor\symfony"
    "thecodingmachine"    = "vendor\thecodingmachine"
}

$smallDirs = @()
foreach ($t in $topDirs) {
    $isBig = $false
    foreach ($b in $big.Values) { if ($t.FullName -eq (Join-Path (Get-Location) $b)) { $isBig = $true; break } }
    if (-not $isBig) { $smallDirs += $t }
}
# small laravel sub-packages that aren't framework
foreach ($sub in @("agent-detector","prompts","sanctum","serializable-closure","tinker")) {
    $p = "vendor\laravel\$sub"
    if (Test-Path $p) { $smallDirs += (Get-Item $p) }
}

# Zip big packages
foreach ($kv in $big.GetEnumerator()) {
    $src = Join-Path (Get-Location) $kv.Value
    $staging = "$outDir\stage-$($kv.Key)"
    if (Test-Path $staging) { Remove-Item -Recurse -Force $staging }
    New-Item -ItemType Directory -Force -Path $staging | Out-Null
    Copy-Item $src $staging -Recurse -Force
    $zip = "$outDir\v-$($kv.Key).zip"
    Compress-Archive -Path "$staging\*" -DestinationPath $zip -Force
    Write-Host ("ZIP {0}: {1:N1}MB" -f $kv.Key, ((Get-Item $zip).Length/1MB))
}

# Zip small dirs together (should be well under 25MB)
$smallDir = "$outDir\stage-small"
if (Test-Path $smallDir) { Remove-Item -Recurse -Force $smallDir }
New-Item -ItemType Directory -Force -Path $smallDir | Out-Null
foreach ($d in $smallDirs) {
    $dest = Join-Path $smallDir $d.Name
    Copy-Item $d.FullName $dest -Recurse -Force
}
$zipSmall = "$outDir\v-small.zip"
Compress-Archive -Path "$smallDir\*" -DestinationPath $zipSmall -Force
Write-Host ("ZIP small: {0:N1}MB" -f ((Get-Item $zipSmall).Length/1MB))

Write-Host "=== DONE ==="
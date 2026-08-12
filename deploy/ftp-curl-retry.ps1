$outDir = "C:\Users\ardis\AppData\Local\Temp\opencode\parts"
$ftpHost = "ftpupload.net"
$ftpUser = "if0_42632547"
$ftpPass = "oBWjoe401zar"

$missing = @("v-nesbot.zip","v-phpoffice.zip","v-psy.zip","v-small.zip","v-symfony.zip","v-thecodingmachine.zip")

foreach ($name in $missing) {
    $path = Join-Path $outDir $name
    if (-not (Test-Path $path)) { Write-Host "SKIP (not found): $name"; continue }
    Write-Host ("Uploading " + $name + " (" + [math]::Round((Get-Item $path).Length/1MB,1) + "MB) ...")
    $args = @(
        "--silent", "--show-error",
        "--ftp-create-dirs",
        ("ftp://$ftpHost/htdocs/parts/" + $name),
        "--upload-file", $path,
        "--user", "${ftpUser}:${ftpPass}"
    )
    & curl.exe @args 2>&1
    Write-Host ("  exit: " + $LASTEXITCODE)
}
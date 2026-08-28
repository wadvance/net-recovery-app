param(
    [string]$LocalBase,
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "if0_42632547",
    [string]$FtpPass = "oBWjoe401zar",
    [string]$RemoteDir = "/htdocs/admin"
)

$base = Get-Item $LocalBase
$files = Get-ChildItem $base.FullName -Recurse -File

Write-Host "=== Subiendo $($files.Count) archivos a $RemoteDir ==="
foreach ($f in $files) {
    $rel = $f.FullName.Substring($base.FullName.Length).TrimStart('\').Replace('\','/')
    $rem = "$RemoteDir/$rel".Replace('//','/')
    Write-Host "Subiendo: $rel"
    & curl.exe --silent --show-error --ftp-create-dirs --upload-file $f.FullName "ftp://$FtpHost$rem" --user "${FtpUser}:${FtpPass}" --connect-timeout 30 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "OK  $rel"
    } else {
        Write-Host "FAIL $rel (exit $LASTEXITCODE)"
    }
}
Write-Host "=== Subida completada ==="
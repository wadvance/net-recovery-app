$ErrorActionPreference = "Stop"
$zip = "C:\PROYECTOS\NET RECOVERY - APP\recovery-app\deploy\ezyro\ezyro-htdocs.zip"
$outDir = "C:\Users\ardis\AppData\Local\Temp\opencode\ezyro-parts"
if (Test-Path $outDir) { Remove-Item $outDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$chunkSize = 4MB
$fs = [System.IO.File]::OpenRead($zip)
$buffer = New-Object byte[] $chunkSize
$part = 1
$total = [math]::Ceiling($fs.Length / $chunkSize)
while ($true) {
    $read = $fs.Read($buffer, 0, $chunkSize)
    if ($read -le 0) { break }
    $name = "netrecovery.zip.{0:D3}" -f $part
    $out = [System.IO.File]::Create((Join-Path $outDir $name))
    $out.Write($buffer, 0, $read)
    $out.Close()
    Write-Host ("{0}: {1:N2} MB" -f $name, ($read/1MB))
    $part++
}
$fs.Close()
Write-Host "Partes: $($part-1) de $total"
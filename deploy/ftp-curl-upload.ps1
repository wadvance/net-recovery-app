$outDir = "C:\Users\ardis\AppData\Local\Temp\opencode\parts"
$ftpHost = "ftpupload.net"
$ftpUser = "if0_42632547"
$ftpPass = "oBWjoe401zar"

# ensure parts dir on server
curl.exe --silent --ftp-create-dirs "ftp://$ftpHost/htdocs/parts/" --user "${ftpUser}:${ftpPass}" --connect-timeout 30 | Out-Null

$jobs = @()
foreach ($zip in Get-ChildItem $outDir -Filter "v-*.zip") {
    Write-Host ("Uploading " + $zip.Name + " ...")
    $p = Start-Process -FilePath "curl.exe" -ArgumentList @(
        "--silent",
        "--show-error",
        "--ftp-create-dirs",
        ("ftp://$ftpHost/htdocs/parts/" + $zip.Name),
        "--upload-file", ("`"$($zip.FullName)`""),
        "--user", "`"${ftpUser}:${ftpPass}`"",
        "--connect-timeout", "30"
    ) -NoNewWindow -PassThru
    $jobs += $p
}
foreach ($j in $jobs) { $j.WaitForExit() | Out-Null }
Write-Host "=== All uploads finished ==="
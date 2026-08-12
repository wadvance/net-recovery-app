param(
    [string]$ZipPath = "C:\Users\ardis\AppData\Local\Temp\opencode\infinityfree-out\netrecovery.zip",
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "if0_42632547",
    [string]$FtpPass = "oBWjoe401zar"
)

$cred = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)

# Upload to a temp name first
$tmp = "/htdocs/upload_tmp.zip"
$req = [System.Net.FtpWebRequest]::Create("ftp://$FtpHost$tmp")
$req.Credentials = $cred
$req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
$req.UseBinary = $true
$req.UsePassive = $true
$req.KeepAlive = $false

$fs = [System.IO.File]::OpenRead($ZipPath)
$req.ContentLength = $fs.Length
$stream = $req.GetRequestStream()
$fs.CopyTo($stream)
$stream.Close()
$fs.Close()
$resp = $req.GetResponse()
Write-Host "Upload status: $($resp.StatusDescription)"
$resp.Close()

Start-Sleep -Seconds 5

# Verify file size on server
try {
    $siz = [System.Net.FtpWebRequest]::Create("ftp://$FtpHost$tmp")
    $siz.Credentials = $cred
    $siz.Method = "SIZE"
    $siz.UsePassive = $true
    $siz.KeepAlive = $false
    $sr = $siz.GetResponse()
    Write-Host "Server size: $($sr.ContentLength)"
    $sr.Close()
} catch {
    Write-Host "SIZE check FAILED: $($_.Exception.InnerException.Message)"
}
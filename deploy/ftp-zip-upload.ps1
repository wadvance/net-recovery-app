param(
    [string]$ZipPath = "C:\Users\ardis\AppData\Local\Temp\opencode\infinityfree-out\netrecovery.zip",
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "if0_42632547",
    [string]$FtpPass = "oBWjoe401zar"
)

$cred = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
$uri = "ftp://$FtpHost/htdocs/netrecovery.zip"

$req = [System.Net.FtpWebRequest]::Create($uri)
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
Write-Host "Status: " $resp.StatusDescription
$resp.Close()
Write-Host "Upload complete"
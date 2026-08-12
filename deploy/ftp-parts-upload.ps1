param(
    [string]$PartsDir = "C:\Users\ardis\AppData\Local\Temp\opencode\parts",
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "if0_42632547",
    [string]$FtpPass = "oBWjoe401zar"
)

$cred = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)

foreach ($zip in Get-ChildItem $PartsDir -Filter *.zip) {
    $remote = "/htdocs/parts/$($zip.Name)"
    try {
        # ensure dir
        $md = [System.Net.FtpWebRequest]::Create("ftp://$FtpHost/htdocs/parts")
        $md.Credentials = $cred
        $md.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $md.KeepAlive = $false
        try { $mdr = $md.GetResponse(); $mdr.Close() } catch {}

        $uri = "ftp://$FtpHost$remote"
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Credentials = $cred
        $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $req.UseBinary = $true
        $req.UsePassive = $true
        $req.KeepAlive = $false
        $fs = [System.IO.File]::OpenRead($zip.FullName)
        $req.ContentLength = $fs.Length
        $stream = $req.GetRequestStream()
        $fs.CopyTo($stream)
        $stream.Close()
        $fs.Close()
        $resp = $req.GetResponse()
        Write-Host ("OK {0} ({1:N1} MB) - {2}" -f $zip.Name, ($zip.Length/1MB), $resp.StatusDescription)
        $resp.Close()
    } catch {
        Write-Host ("FAIL {0}: {1}" -f $zip.Name, $_.Exception.Message)
    }
}
param(
    [string]$LocalBase = "C:\PROYECTOS\NET RECOVERY - APP\recovery-app\deploy\infinityfree\htdocs",
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "if0_42632547",
    [string]$FtpPass = "oBWjoe401zar",
    [string]$RemoteBase = "/htdocs"
)

Add-Type -AssemblyName System.Net

function Ftp-Request {
    param($Uri, $Method, $Cred)
    $req = [System.Net.FtpWebRequest]::Create($Uri)
    $req.Credentials = $Cred
    $req.Method = $Method
    $req.UseBinary = $true
    $req.UsePassive = $true
    $req.KeepAlive = $true
    return $req
}

$cred = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)

function Ensure-Dir($remote) {
    $parts = $remote -split '/'
    $path = ""
    foreach ($p in $parts) {
        if ($p -eq "" -or $p -eq ".") { continue }
        $path = "$path/$p"
        try {
            $req = Ftp-Request -Uri "ftp://$FtpHost$path" -Method ([System.Net.WebRequestMethods+Ftp]::MakeDirectory) -Cred $cred
            $resp = $req.GetResponse()
            $resp.Close()
        } catch {
            # dir may already exist
        }
    }
}

function Upload-Files($localDir, $remoteDir) {
    $items = Get-ChildItem $localDir -Force
    foreach ($item in $items) {
        $rel = $item.FullName.Substring($LocalBase.Length).TrimStart('\').Replace('\','/')
        $remPath = "$remoteDir/$rel"
        if ($item.PSIsContainer) {
            Ensure-Dir $remPath
            Upload-Files $item.FullName $remoteDir
        } else {
            $uri = "ftp://$FtpHost$remPath"
            try {
                $req = Ftp-Request -Uri $uri -Method ([System.Net.WebRequestMethods+Ftp]::UploadFile) -Cred $cred
                $fs = [System.IO.File]::OpenRead($item.FullName)
                $req.ContentLength = $fs.Length
                $stream = $req.GetRequestStream()
                $fs.CopyTo($stream)
                $stream.Close()
                $fs.Close()
                $resp = $req.GetResponse()
                $resp.Close()
                Write-Host "OK  $rel"
            } catch {
                Write-Host "FAIL $rel :: $($_.Exception.Message)"
            }
        }
    }
}

Write-Host "=== Starting upload ==="
Ensure-Dir $RemoteBase
Upload-Files $LocalBase $RemoteBase
Write-Host "=== Upload complete ==="
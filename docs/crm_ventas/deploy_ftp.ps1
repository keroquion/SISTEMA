# ==========================================================
# PETULAP SALES CRM - DESPLIEGUE FTP NATIVO EN POWERSHELL
# Sube de forma aislada crm_ventas/ a public_html/crm_ventas/
# ==========================================================

$FTP_HOST = "ftp.petulap.store"
$FTP_USER = "petumjvq"
$FTP_PASS = "HjBI32sh5kAb"
$LOCAL_DIR = "$PSScriptRoot"
$REMOTE_BASE = "ftp://$FTP_HOST/public_html/crm_ventas"

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "INICIANDO SUBIDA AISLADA DE CRM VENTAS POR POWERSHELL" -ForegroundColor Cyan
Write-Host "Local:  $LOCAL_DIR"
Write-Host "Remoto: $REMOTE_BASE"
Write-Host "============================================================" -ForegroundColor Cyan

function Make-FtpDir {
    param([string]$targetUri)
    try {
        $req = [System.Net.FtpWebRequest]::Create($targetUri)
        $req.Credentials = New-Object System.Net.NetworkCredential($FTP_USER, $FTP_PASS)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $req.UsePassive = $true
        $res = $req.GetResponse()
        $res.Close()
        Write-Host "Directorio remoto creado: $targetUri" -ForegroundColor Green
    }
    catch {
        $null = $_.Exception.Message
    }
}

function Send-FtpFile {
    param([string]$sourcePath, [string]$targetUri)
    try {
        $req = [System.Net.FtpWebRequest]::Create($targetUri)
        $req.Credentials = New-Object System.Net.NetworkCredential($FTP_USER, $FTP_PASS)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $req.UseBinary = $true
        $req.UsePassive = $true
        
        $bytes = [System.IO.File]::ReadAllBytes($sourcePath)
        $req.ContentLength = $bytes.Length
        $stream = $req.GetRequestStream()
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        
        $res = $req.GetResponse()
        $res.Close()
        Write-Host "Subido: $targetUri" -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "Error subiendo: $targetUri ($($_.Exception.Message))" -ForegroundColor Red
        return $false
    }
}

# 1. Crear directorio base
Make-FtpDir -targetUri $REMOTE_BASE

# 2. Subdirectorios
$dirs = @("api", "css", "js", "extension")
foreach ($d in $dirs) {
    Make-FtpDir -targetUri "$REMOTE_BASE/$d"
}

# 3. Subir archivos
$files = Get-ChildItem -Path $LOCAL_DIR -Recurse -File | Where-Object { 
    $_.Extension -notin @(".ps1", ".py", ".sqlite") -and 
    $_.FullName -notmatch "\\storage\\" 
}

$uploaded = 0
foreach ($f in $files) {
    $rel = $f.FullName.Substring($LOCAL_DIR.Length).TrimStart("\").Replace("\", "/")
    $dest = "$REMOTE_BASE/$rel"
    if (Send-FtpFile -sourcePath $f.FullName -targetUri $dest) {
        $uploaded++
    }
}

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "DESPLIEGUE FINALIZADO: $uploaded archivos subidos exitosamente." -ForegroundColor Green
Write-Host "Enlace en produccion: https://petulap.store/crm_ventas/index.html" -ForegroundColor Yellow
Write-Host "============================================================" -ForegroundColor Cyan

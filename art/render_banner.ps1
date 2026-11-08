# CHIM Travel Fix - renders art/banner.html (written by make_banner.py) to art/banner.png
# (1920x1080) with Microsoft Edge in headless mode (any Chromium browser works the same way),
# and writes the smaller copy the FOMOD installer shows: fomod/images/banner.jpg (1280x720).
#
#   python3 -I art\make_banner.py
#   .\art\render_banner.ps1

$ErrorActionPreference = 'Stop'
$art = $PSScriptRoot
$edge = @("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
          "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $edge) { throw "Microsoft Edge not found" }
$html = (Join-Path $art 'banner.html') -replace '\\', '/'
$png = Join-Path $art 'banner.png'
Remove-Item $png -ErrorAction SilentlyContinue
Start-Process -FilePath $edge -Wait -WindowStyle Hidden -ArgumentList @(
    '--headless=new', '--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1',
    '--window-size=1920,1080', "--screenshot=`"$png`"", "`"file:///$html`"")
if (-not (Test-Path $png)) { throw "banner.png was not written" }
Write-Host "written $png"

# FOMOD picture: 1280x720 JPEG, quality 88
Add-Type -AssemblyName System.Drawing
$jpg = Join-Path (Split-Path $art -Parent) 'fomod\images\banner.jpg'
New-Item -ItemType Directory -Force (Split-Path $jpg -Parent) | Out-Null
$src = [System.Drawing.Image]::FromFile($png)
$dst = New-Object System.Drawing.Bitmap 1280, 720
$g = [System.Drawing.Graphics]::FromImage($dst)
$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$g.DrawImage($src, 0, 0, 1280, 720)
$codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
$params = New-Object System.Drawing.Imaging.EncoderParameters 1
$params.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter ([System.Drawing.Imaging.Encoder]::Quality), 88L
$dst.Save($jpg, $codec, $params)
$g.Dispose(); $dst.Dispose(); $src.Dispose()
Write-Host "written $jpg"

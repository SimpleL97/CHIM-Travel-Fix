# CHIM Travel Fix by SaurOff - installs the server plugin into the CHIM server.
#
# CHIM's server (HerikaServer) runs inside a WSL distro, normally "DwemerAI4Skyrim3".
# This copies the "chim_travel_fix" folder next to this script into
# /var/www/html/HerikaServer/ext/ of that distro.

$ErrorActionPreference = 'Stop'
$src = Join-Path $PSScriptRoot 'chim_travel_fix'
if (-not (Test-Path $src)) {
    Write-Host "The folder 'chim_travel_fix' was not found next to this script." -ForegroundColor Red
    exit 1
}

# wsl -l -q prints UTF-16 with NUL bytes; clean it up
$distros = @(wsl.exe -l -q 2>$null | ForEach-Object { ($_ -replace "`0", '').Trim() } | Where-Object { $_ -ne '' })
$candidates = @('DwemerAI4Skyrim3') + $distros | Select-Object -Unique
$target = $null
foreach ($d in $candidates) {
    if ($distros -notcontains $d) { continue }
    wsl.exe -d $d -- test -d /var/www/html/HerikaServer/ext 2>$null
    if ($LASTEXITCODE -eq 0) { $target = $d; break }
}
if (-not $target) {
    Write-Host "No WSL distro with a CHIM server (/var/www/html/HerikaServer) was found." -ForegroundColor Red
    Write-Host "Install and start CHIM (DwemerAI) once, then run this again."
    exit 1
}

$winPath = ($src -replace '\\', '/')
$wslSrc = (wsl.exe -d $target -- wslpath -a "$winPath").Trim()
$ext = '/var/www/html/HerikaServer/ext'
$cmd = "rm -rf '$ext/chim_travel_fix' && cp -r '$wslSrc' '$ext/' && chown -R dwemer:www-data '$ext/chim_travel_fix' && chmod -R ug+rwX '$ext/chim_travel_fix'"
wsl.exe -d $target -u root -- bash -c $cmd
if ($LASTEXITCODE -ne 0) {
    Write-Host "Copying failed (exit code $LASTEXITCODE)." -ForegroundColor Red
    exit 1
}
Write-Host "CHIM Travel Fix server plugin installed into '$target' ($ext/chim_travel_fix)." -ForegroundColor Green
Write-Host "No restart needed: CHIM loads it on the next request."

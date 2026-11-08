# CHIM Travel Fix by SaurOff - removes the server plugin. It keeps nothing in CHIM's database.

$ErrorActionPreference = 'Stop'
$distros = @(wsl.exe -l -q 2>$null | ForEach-Object { ($_ -replace "`0", '').Trim() } | Where-Object { $_ -ne '' })
$candidates = @('DwemerAI4Skyrim3') + $distros | Select-Object -Unique
$target = $null
foreach ($d in $candidates) {
    if ($distros -notcontains $d) { continue }
    wsl.exe -d $d -- test -d /var/www/html/HerikaServer/ext 2>$null
    if ($LASTEXITCODE -eq 0) { $target = $d; break }
}
if (-not $target) {
    Write-Host "No WSL distro with a CHIM server was found." -ForegroundColor Red
    exit 1
}
wsl.exe -d $target -u root -- bash -c "rm -rf /var/www/html/HerikaServer/ext/chim_travel_fix"
Write-Host "CHIM Travel Fix server plugin removed from '$target'." -ForegroundColor Green

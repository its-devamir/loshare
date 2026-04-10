Set-Location $PSScriptRoot
$Port = 8080
if ($args[0] -match '^\d+$') { $Port = [int]$args[0] }

Write-Host ""
Write-Host " Loshare — http://127.0.0.1:$Port/ on this PC"
Write-Host " On your phone use a URL from the app (this machine's LAN IP)."
Write-Host " Port: $Port  (optional: .\start-loshare.ps1 3000)"
Write-Host " Press Ctrl+C to stop."
Write-Host ""

Start-Process "http://127.0.0.1:$Port/"
php -d upload_max_filesize=8192M -d post_max_size=8192M -d max_file_uploads=200 -d max_execution_time=0 -S "0.0.0.0:$Port" index.php

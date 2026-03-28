Set-Location $PSScriptRoot
Write-Host ""
Write-Host " Loshare — http://127.0.0.1:8080  (this PC)"
Write-Host " On your phone use: http://YOUR_LAN_IP:8080"
Write-Host " Press Ctrl+C to stop."
Write-Host ""
Start-Process "http://127.0.0.1:8080"
php -S 0.0.0.0:8080

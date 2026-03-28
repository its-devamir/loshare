@echo off
cd /d "%~dp0"
echo.
echo  Loshare — http://127.0.0.1:8080  (this PC)
echo  On your phone use: http://YOUR_LAN_IP:8080
echo  Press Ctrl+C to stop.
echo.
start "" "http://127.0.0.1:8080"
php -S 0.0.0.0:8080

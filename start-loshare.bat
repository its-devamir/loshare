@echo off
cd /d "%~dp0"
set PORT=8080
if not "%~1"=="" set PORT=%~1

echo.
echo  Loshare — http://127.0.0.1:%PORT%  on this PC
echo  On your phone use one of the URLs shown in the app (this PC's LAN IP).
echo  Optional: use another port — start-loshare.bat 3000
echo  Press Ctrl+C to stop.
echo.

start "" "http://127.0.0.1:%PORT%/"
php -d upload_max_filesize=8192M -d post_max_size=8192M -d max_file_uploads=200 -d max_execution_time=0 -S 0.0.0.0:%PORT% index.php

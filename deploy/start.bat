@echo off
rem Smart Mart POS launcher: starts the local server (hidden) if needed, then opens the app window.
cd /d "%~dp0app"

curl -s -o nul http://127.0.0.1:8000/up && goto open

powershell -NoProfile -WindowStyle Hidden -Command "Start-Process -FilePath '%~dp0php\php.exe' -ArgumentList 'artisan','serve','--host=127.0.0.1','--port=8000' -WorkingDirectory '%~dp0app' -WindowStyle Hidden"

rem Wait up to ~15 seconds for the server to answer.
for /l %%i in (1,1,15) do (
    curl -s -o nul http://127.0.0.1:8000/up && goto open
    timeout /t 1 /nobreak >nul
)

:open
start "" msedge --app=http://127.0.0.1:8000/admin/pos --kiosk-printing --user-data-dir="%~dp0browser-profile"

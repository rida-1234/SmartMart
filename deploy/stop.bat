@echo off
rem Stops the Smart Mart server (only the PHP processes started from this folder).
powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" | Where-Object { $_.ExecutablePath -like '%~dp0php\*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }"
echo Smart Mart stopped.

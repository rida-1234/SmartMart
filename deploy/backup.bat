@echo off
rem Daily backup, run by Windows Task Scheduler (created by the installer).
cd /d "%~dp0app"
"%~dp0php\php.exe" artisan smartmart:backup >> storage\logs\backup.log 2>&1

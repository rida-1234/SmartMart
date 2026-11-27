# Builds dist\bundle (portable PHP + built app + .bat files), ready for Inno Setup.
# Run on the Windows dev machine from the project root:  powershell -ExecutionPolicy Bypass -File deploy\build.ps1
#
# One-time preparation (not in Git):
#   deploy\runtime\php\               <- unzip PHP 8.3+ "VS16/VS17 x64 Non Thread Safe" from https://windows.php.net/download/
#   deploy\runtime\vc_redist.x64.exe  <- from https://aka.ms/vs/17/release/vc_redist.x64.exe

$ErrorActionPreference = 'Stop'
$root   = Split-Path -Parent $PSScriptRoot
$bundle = Join-Path $root 'dist\bundle'
$app    = Join-Path $bundle 'app'

foreach ($need in @('deploy\runtime\php\php.exe', 'deploy\runtime\vc_redist.x64.exe')) {
    if (-not (Test-Path (Join-Path $root $need))) { throw "Missing $need (see the top of this script)." }
}

Write-Host '1/5 Copying the app...'
if (Test-Path $bundle) { Remove-Item $bundle -Recurse -Force }
New-Item -ItemType Directory -Force -Path $app | Out-Null
$skipDirs = 'node_modules', 'vendor', 'tests', '.git', 'dist', 'deploy', 'storage\backups' | ForEach-Object { Join-Path $root $_ }
robocopy $root $app /E /NFL /NDL /NJH /NJS /XD @skipDirs /XF .env database.sqlite database.sqlite-wal database.sqlite-shm *.log | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed ($LASTEXITCODE)" }

Write-Host '2/5 Installing PHP dependencies (no dev)...'
Push-Location $app
composer install --no-dev --optimize-autoloader --no-interaction

Write-Host '3/5 Writing production .env...'
Copy-Item .env.example .env -Force
(Get-Content .env) `
    -replace '^APP_ENV=.*', 'APP_ENV=production' `
    -replace '^APP_DEBUG=.*', 'APP_DEBUG=false' `
    -replace '^APP_URL=.*', 'APP_URL=http://127.0.0.1:8000' `
    -replace '^LOG_LEVEL=.*', 'LOG_LEVEL=warning' |
    Set-Content .env
php artisan key:generate --force

Write-Host '4/5 Creating the starter database...'
New-Item -ItemType File -Force -Path 'database\database.sqlite' | Out-Null
php artisan migrate --force --seed
# Fold the WAL into the main file so only database.sqlite ships.
php -r "`$p=new PDO('sqlite:database/database.sqlite'); `$p->exec('PRAGMA wal_checkpoint(TRUNCATE); PRAGMA journal_mode=DELETE;');"
Remove-Item 'database\database.sqlite-wal','database\database.sqlite-shm' -ErrorAction SilentlyContinue
php artisan optimize:clear
Pop-Location

Write-Host '5/5 Adding PHP runtime and launch scripts...'
Copy-Item (Join-Path $root 'deploy\runtime\php') (Join-Path $bundle 'php') -Recurse
Copy-Item (Join-Path $root 'deploy\php.ini') (Join-Path $bundle 'php\php.ini') -Force
Copy-Item (Join-Path $root 'deploy\runtime\vc_redist.x64.exe') $bundle
foreach ($f in 'start.bat', 'stop.bat', 'backup.bat') { Copy-Item (Join-Path $root "deploy\$f") $bundle }

Write-Host "Done: $bundle"
Write-Host 'Next: open deploy\setup.iss in Inno Setup and press Compile (Ctrl+F9).'

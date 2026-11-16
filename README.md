# Smart Mart POS

An offline POS and inventory system for a small mart. It runs on one Windows PC with Laravel and SQLite, and ships as a portable PHP bundle with a one-click installer.

## Status

| Part | State |
| --- | --- |
| Database schema (14 shop tables + users) | Done: `database/migrations` |
| Models | Done: `app/Models` |
| Checkout, returns, purchases, payments, stock adjustment | Done: `app/Services` |
| Reports (summary, profit, expected cash, top products, stock value, dues) | Done: `app/Services/ReportService.php` |
| Backup command (`php artisan smartmart:backup`) | Done |
| Thermal receipt (80 mm) | Done: `/receipts/{sale}?print=1` |
| Tests (21: money/stock flows, every screen, cashier limits, POS, import) | Passing: `php artisan test` |
| Windows packaging (start/stop/backup .bat, php.ini, build.ps1, Inno Setup) | Done: `deploy/` |
| Admin panel: products (CSV import, stock adjust), purchases, suppliers, customers, bills (reprint/return), expenses, users | Done: `app/Filament/Resources` |
| POS billing screen: scan/search, qty, discount, cash/card/wallet/credit, hold bills, auto-print | Done: `app/Filament/Pages/Pos.php` |
| Reports + day closing, Settings (shop details, backup now), dashboard | Done: `app/Filament/Pages`, `app/Filament/Widgets` |

## Develop

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan test
php artisan serve
```

Open http://127.0.0.1:8000. The seeded login is `admin@smartmart.local` / `admin123`. Change it at first login.

## Ship to the shop (Windows)

1. Put the PHP for Windows zip (x64, NTS) unzipped in `deploy/runtime/php/` and `vc_redist.x64.exe` in `deploy/runtime/`.
2. Run `powershell -ExecutionPolicy Bypass -File deploy\build.ps1`.
3. Open `deploy/setup.iss` in Inno Setup, set your own `AppId` GUID, and compile.
4. Run `dist/SmartMart-Setup-x.y.z.exe` on the shop PC.

The same installer is used for updates. It backs up the database, keeps shop data, and runs new migrations.

## Rules that keep the numbers right

- Never write to `stock` directly. Go through the services, which also write a `stock_movements` row.
- Bills are never deleted. Returns are recorded with `CheckoutService::returnItem()`.
- `sale_items` stores `name` and `cost_price` at the time of sale, so old bills and profit don't change when prices change.
- Never edit a migration that has already shipped. Add a new one.

## Who sees what

| Screen | Owner (admin) | Cashier |
| --- | --- | --- |
| POS / Billing, Customers (receive credit payments) | Yes | Yes |
| Bills | All bills, can return items | Own bills only, reprint |
| Products, Purchases, Suppliers, Categories, Units | Yes | No |
| Expenses, Reports, Users, Settings, Dashboard figures | Yes | No |

## Billing keys

Enter = add item · F2 = pay and print · F8 = hold bill · F9 = amount received · Esc = clear bill.
Leave "amount received" empty for exact cash.

## Importing products

Products > Import CSV. In Excel, save the sheet as CSV with the columns
`name, barcode, category, unit, cost_price, sale_price, stock, low_stock_level` (only `name` is required).
Rows are matched by barcode, then by name; `stock` sets the counted stock and is logged as an adjustment.

## Restoring a backup

1. Run `stop.bat`.
2. Copy the chosen file from `C:\SmartMart\app\storage\backups` (or the extra backup folder) over `C:\SmartMart\app\database\database.sqlite`.
3. Delete `database.sqlite-wal` and `database.sqlite-shm` in the same folder if they exist.
4. Run `start.bat`.

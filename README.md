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
| Feature tests for the money and stock flows | Written: `tests/Feature/ShopFlowTest.php` |
| Windows packaging (start/stop/backup .bat, php.ini, build.ps1, Inno Setup) | Done: `deploy/` |
| Filament admin panel and resources | To do |
| POS billing screen (Filament page + Livewire) | To do |

## Develop

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan test
php artisan serve
```

The seeded login is `admin@smartmart.local` / `admin123`. Change it at first login.

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

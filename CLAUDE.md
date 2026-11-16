# Smart Mart POS: notes for contributors

Laravel 13 + Filament 5 + Livewire 4 + SQLite, built to run offline on one Windows PC.

- Business rules live in `app/Services`. Never change `products.stock` or customer/supplier `balance` directly; go through `CheckoutService`, `PurchaseService` or `LedgerService`, which also write `stock_movements` / `payments`.
- Bills and purchases are never edited or deleted. Returns use `CheckoutService::returnItem()`.
- `sale_items` stores `name` and `cost_price` at the time of sale; reports depend on it.
- Never edit a shipped migration; add a new one.
- Run `php artisan test` before committing.
- Windows packaging is in `deploy/` (see README).

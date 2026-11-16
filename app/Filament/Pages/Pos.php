<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Product;
use App\Services\CheckoutService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * Billing screen. The barcode box keeps focus: scan (scanner types the code
 * and presses Enter) or type part of a name and pick from the list.
 * Keys: F2 = pay, F8 = hold bill, F9 = amount received, Esc = clear bill.
 */
class Pos extends Page
{
    protected string $view = 'filament.pages.pos';

    protected static ?string $title = 'Billing';

    protected static ?string $navigationLabel = 'POS / Billing';

    protected static ?string $slug = 'pos';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $scan = '';

    /** @var array<int|string, array{name:string, price:float, qty:float, stock:float, unit:?string}> keyed by product id */
    public array $cart = [];

    public ?string $billDiscount = null;

    public ?string $paid = null;

    public string $method = 'cash';

    public ?int $customerId = null;

    #[Computed]
    public function results(): Collection
    {
        $term = trim($this->scan);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Product::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('barcode', 'like', "{$term}%"))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'barcode', 'sale_price', 'stock']);
    }

    #[Computed]
    public function customers(): Collection
    {
        return Customer::orderBy('name')->get(['id', 'name', 'phone', 'balance']);
    }

    #[Computed]
    public function subtotal(): float
    {
        return round(collect($this->cart)->sum(fn ($l) => $l['price'] * (float) $l['qty']), 2);
    }

    #[Computed]
    public function total(): float
    {
        return round(max(0, $this->subtotal - (float) $this->billDiscount), 2);
    }

    #[Computed]
    public function change(): float
    {
        return round(max(0, (float) $this->paid - $this->total), 2);
    }

    #[Computed]
    public function held(): array
    {
        return session('pos.held', []);
    }

    /** Enter in the scan box: exact barcode first, else the only name match. */
    public function addByScan(): void
    {
        $term = trim($this->scan);
        if ($term === '') {
            return;
        }

        $product = Product::where('is_active', true)->where('barcode', $term)->first();

        if (! $product) {
            $matches = $this->results;
            $product = $matches->count() === 1 ? $matches->first() : null;
        }

        if (! $product) {
            $this->dispatch('pos-beep');
            Notification::make()->title("Not found: {$term}")->danger()->send();

            return;
        }

        $this->addProduct($product->id);
    }

    public function addProduct(int $productId): void
    {
        $product = Product::with('unit')->find($productId);
        if (! $product || ! $product->is_active) {
            return;
        }

        $key = (string) $product->id;
        if (isset($this->cart[$key])) {
            $this->cart[$key]['qty'] = (float) $this->cart[$key]['qty'] + 1;
        } else {
            $this->cart[$key] = [
                'name' => $product->name,
                'price' => (float) $product->sale_price,
                'qty' => 1,
                'stock' => (float) $product->stock,
                'unit' => $product->unit?->short_name,
            ];
        }

        // Move the latest item to the top of the list.
        $this->cart = [$key => $this->cart[$key]] + $this->cart;
        $this->scan = '';
        unset($this->results);
    }

    public function updatedCart($value, string $key): void
    {
        // Typing a quantity of 0 or less removes the line.
        if (str_ends_with($key, '.qty') && (float) $value <= 0) {
            unset($this->cart[explode('.', $key)[0]]);
        }
    }

    public function remove(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function clear(): void
    {
        $this->reset('cart', 'scan', 'billDiscount', 'paid', 'customerId');
        $this->method = 'cash';
    }

    public function hold(): void
    {
        if ($this->cart === []) {
            return;
        }

        $held = session('pos.held', []);
        $held[] = [
            'at' => now()->format('H:i'),
            'cart' => $this->cart,
            'customerId' => $this->customerId,
            'total' => $this->total,
        ];
        session(['pos.held' => $held]);
        unset($this->held);

        $this->clear();
        Notification::make()->title('Bill put on hold')->send();
    }

    public function resume(int $index): void
    {
        $held = session('pos.held', []);
        if (! isset($held[$index])) {
            return;
        }

        if ($this->cart !== []) {
            $this->hold();
            $held = session('pos.held', []);
        }

        $bill = $held[$index];
        unset($held[$index]);
        session(['pos.held' => array_values($held)]);
        unset($this->held);

        $this->cart = $bill['cart'];
        $this->customerId = $bill['customerId'];
    }

    public function pay(CheckoutService $checkout): void
    {
        $this->resetErrorBag();

        if ($this->cart === []) {
            return;
        }

        // Empty "amount received" means exact cash.
        $paid = ($this->paid === null || $this->paid === '') && $this->method !== 'credit'
            ? $this->total
            : (float) $this->paid;

        $lines = collect($this->cart)
            ->map(fn ($l, $id) => ['product_id' => (int) $id, 'qty' => (float) $l['qty']])
            ->values()
            ->all();

        $sale = $checkout->complete(
            lines: $lines,
            paid: $paid,
            method: $this->method === 'credit' ? 'cash' : $this->method,
            customerId: $this->customerId,
            billDiscount: (float) $this->billDiscount,
        );

        $change = (float) $sale->change_due;
        $this->clear();

        Notification::make()
            ->title("Saved {$sale->invoice_no}")
            ->body($change > 0 ? 'Change to give: '.number_format($change, 2) : null)
            ->success()
            ->send();

        $this->dispatch('pos-print', url: route('receipts.show', ['sale' => $sale, 'print' => 1]));
    }
}

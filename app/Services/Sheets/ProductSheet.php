<?php

namespace App\Services\Sheets;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\LedgerService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Products: matched by barcode, then by exact name.
 * A "stock" value sets the counted stock through LedgerService::adjustStock,
 * so every change is logged in stock_movements.
 */
class ProductSheet extends Sheet
{
    public function __construct(private LedgerService $ledger) {}

    public function name(): string
    {
        return 'products';
    }

    public function columns(): array
    {
        return ['name', 'barcode', 'category', 'unit', 'cost_price', 'sale_price', 'stock', 'low_stock_level', 'expiry_date', 'active'];
    }

    public function aliases(): array
    {
        return [
            'product' => 'name', 'product_name' => 'name', 'item' => 'name',
            'code' => 'barcode', 'sku' => 'barcode',
            'cost' => 'cost_price', 'purchase_price' => 'cost_price', 'buying_price' => 'cost_price',
            'price' => 'sale_price', 'selling_price' => 'sale_price', 'retail_price' => 'sale_price',
            'qty' => 'stock', 'quantity' => 'stock',
            'low_stock' => 'low_stock_level', 'reorder_level' => 'low_stock_level',
            'expiry' => 'expiry_date', 'is_active' => 'active',
        ];
    }

    public function query(): Builder
    {
        return Product::query()->orderBy('name');
    }

    public function exportRows(Builder $query): iterable
    {
        return parent::exportRows($query->with(['category', 'unit']));
    }

    public function exportRow(mixed $p): array
    {
        return [
            $p->name,
            $p->barcode, // written as text, so long barcodes keep every digit
            $p->category?->name,
            $p->unit?->short_name,
            (float) $p->cost_price,
            (float) $p->sale_price,
            (float) $p->stock,
            (float) $p->low_stock_level,
            $p->expiry_date?->toDateString(),
            $p->is_active ? 'yes' : 'no',
        ];
    }

    protected function importRow(array $row, array &$warnings, int $line): string
    {
        $name = $row['name'];
        $barcode = $row['barcode'] ?? null;

        $product = ($barcode ? Product::where('barcode', $barcode)->first() : null)
            ?? Product::where('name', $name)->first();

        $attributes = array_filter([
            'name' => $name,
            'barcode' => $barcode,
            'category_id' => $this->categoryId($row['category'] ?? null),
            'unit_id' => $this->unitId($row['unit'] ?? null),
            'cost_price' => $this->number($row['cost_price'] ?? null),
            'sale_price' => $this->number($row['sale_price'] ?? null),
            'low_stock_level' => $this->number($row['low_stock_level'] ?? null),
            'expiry_date' => $this->date($row['expiry_date'] ?? null),
            'is_active' => $this->boolean($row['active'] ?? null),
        ], fn ($v) => $v !== null);

        if ($product) {
            $product->update($attributes);
            $outcome = 'updated';
        } else {
            $product = Product::create($attributes + ['stock' => 0]);
            $outcome = 'created';
        }

        $stock = $this->number($row['stock'] ?? null);
        if ($stock !== null) {
            $this->ledger->adjustStock($product, $stock, 'Imported from file');
        }

        return $outcome;
    }

    private function categoryId(?string $name): ?int
    {
        return $name === null ? null : Category::firstOrCreate(['name' => $name])->id;
    }

    private function unitId(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        return (Unit::where('short_name', $name)->orWhere('name', $name)->first()
            ?? Unit::create(['name' => $name, 'short_name' => $name]))->id;
    }
}

<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Imports products from a CSV (Excel > Save As > CSV).
 * Header names are matched loosely: "Sale Price", "sale_price" and "sale price" all work.
 * Products are matched by barcode, then by exact name; a stock column sets the
 * counted stock through a logged adjustment.
 */
class ProductImportService
{
    public function __construct(private LedgerService $ledger) {}

    /** @return array{created:int, updated:int, errors:array<int,string>} */
    public function import(string $path): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        $handle = fopen($path, 'r');
        if (! $handle) {
            $result['errors'][] = 'Could not open the file.';

            return $result;
        }

        $header = fgetcsv($handle, escape: '\\');
        if (! $header) {
            fclose($handle);
            $result['errors'][] = 'The file is empty.';

            return $result;
        }

        // Strip a UTF-8 BOM that Excel adds, then normalise names.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $columns = array_map(fn ($h) => str_replace([' ', '-'], '_', strtolower(trim((string) $h))), $header);

        if (! in_array('name', $columns, true)) {
            fclose($handle);
            $result['errors'][] = 'A "name" column is required.';

            return $result;
        }

        $line = 1;
        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = array_combine($columns, array_pad(array_slice($row, 0, count($columns)), count($columns), null));
            $name = trim((string) ($data['name'] ?? ''));

            if ($name === '') {
                $result['errors'][] = "Row {$line}: missing name";

                continue;
            }

            try {
                DB::transaction(function () use ($data, $name, &$result) {
                    $barcode = trim((string) ($data['barcode'] ?? '')) ?: null;

                    $product = ($barcode ? Product::where('barcode', $barcode)->first() : null)
                        ?? Product::where('name', $name)->first();

                    $attributes = array_filter([
                        'name' => $name,
                        'barcode' => $barcode,
                        'category_id' => $this->categoryId($data['category'] ?? null),
                        'unit_id' => $this->unitId($data['unit'] ?? null),
                        'cost_price' => $this->number($data['cost_price'] ?? null),
                        'sale_price' => $this->number($data['sale_price'] ?? null),
                        'low_stock_level' => $this->number($data['low_stock_level'] ?? null),
                    ], fn ($v) => $v !== null);

                    if ($product) {
                        $product->update($attributes);
                        $result['updated']++;
                    } else {
                        $product = Product::create($attributes + ['stock' => 0]);
                        $result['created']++;
                    }

                    $stock = $this->number($data['stock'] ?? null);
                    if ($stock !== null) {
                        $this->ledger->adjustStock($product, $stock, 'CSV import');
                    }
                });
            } catch (\Throwable $e) {
                $result['errors'][] = "Row {$line}: ".$e->getMessage();
            }
        }

        fclose($handle);

        return $result;
    }

    private function number(mixed $value): ?float
    {
        $value = trim(str_replace(',', '', (string) $value));

        return is_numeric($value) ? (float) $value : null;
    }

    private function categoryId(mixed $name): ?int
    {
        $name = trim((string) $name);

        return $name === '' ? null : Category::firstOrCreate(['name' => $name])->id;
    }

    private function unitId(mixed $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return (Unit::where('short_name', $name)->orWhere('name', $name)->first()
            ?? Unit::create(['name' => $name, 'short_name' => $name]))->id;
    }
}

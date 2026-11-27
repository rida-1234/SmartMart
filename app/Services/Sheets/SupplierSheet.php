<?php

namespace App\Services\Sheets;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

/**
 * Suppliers: matched by name (case-insensitive).
 * "balance" is only used as the opening balance of a NEW supplier. Balances of
 * existing suppliers change only through purchases and payments, never by import.
 */
class SupplierSheet extends Sheet
{
    public function name(): string
    {
        return 'suppliers';
    }

    public function columns(): array
    {
        return ['name', 'phone', 'address', 'balance'];
    }

    public function aliases(): array
    {
        return [
            'supplier' => 'name', 'supplier_name' => 'name', 'company' => 'name',
            'mobile' => 'phone', 'phone_number' => 'phone', 'contact' => 'phone',
            'opening_balance' => 'balance', 'we_owe' => 'balance', 'due' => 'balance',
        ];
    }

    public function query(): Builder
    {
        return Supplier::query()->orderBy('name');
    }

    public function exportRow(mixed $s): array
    {
        return [$s->name, $s->phone, $s->address, (float) $s->balance];
    }

    protected function importRow(array $row, array &$warnings, int $line): string
    {
        $balance = $this->number($row['balance'] ?? null);
        $details = array_filter([
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
        ], fn ($v) => $v !== null);

        $supplier = Supplier::whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])->first();

        if (! $supplier) {
            Supplier::create(['name' => $row['name'], 'balance' => $balance ?? 0] + $details);

            return 'created';
        }

        $supplier->update($details);

        if ($balance !== null && round($balance, 2) != round((float) $supplier->balance, 2)) {
            $warnings[] = "Row {$line}: {$supplier->name} balance not changed (it changes only through purchases and payments)";
        }

        return 'updated';
    }
}

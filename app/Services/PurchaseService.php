<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a supplier invoice: stock goes up, each product's cost price is
 * updated to the latest purchase price, and any unpaid amount is added to
 * the supplier's balance.
 */
class PurchaseService
{
    /**
     * @param  array{supplier_id:int, date:string, invoice_no?:?string, discount?:float, paid?:float, note?:?string}  $data
     * @param  array<int, array{product_id:int, qty:float|int|string, cost_price:float|int|string}>  $lines
     */
    public function record(array $data, array $lines, ?int $userId = null): Purchase
    {
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['qty'] ?? 0) > 0));

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one item.']);
        }

        $userId ??= auth()->id();

        return DB::transaction(function () use ($data, $lines, $userId) {
            $subtotal = 0.0;
            foreach ($lines as &$line) {
                $line['qty'] = round((float) $line['qty'], 3);
                $line['cost_price'] = round((float) $line['cost_price'], 2);
                $line['line_total'] = round($line['qty'] * $line['cost_price'], 2);
                $subtotal += $line['line_total'];
            }
            unset($line);

            $subtotal = round($subtotal, 2);
            $discount = round(min(max((float) ($data['discount'] ?? 0), 0), $subtotal), 2);
            $total = round($subtotal - $discount, 2);
            $paid = round(min(max((float) ($data['paid'] ?? 0), 0), $total), 2);
            $due = round($total - $paid, 2);

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'user_id' => $userId,
                'invoice_no' => $data['invoice_no'] ?? null,
                'date' => $data['date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'due' => $due,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                $purchase->items()->create([
                    'product_id' => $line['product_id'],
                    'qty' => $line['qty'],
                    'cost_price' => $line['cost_price'],
                    'line_total' => $line['line_total'],
                ]);

                Product::whereKey($line['product_id'])->update(['cost_price' => $line['cost_price']]);
                Product::whereKey($line['product_id'])->increment('stock', $line['qty']);

                StockMovement::create([
                    'product_id' => $line['product_id'],
                    'type' => 'purchase',
                    'qty' => $line['qty'],
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'user_id' => $userId,
                ]);
            }

            if ($due > 0) {
                Supplier::whereKey($data['supplier_id'])->increment('balance', $due);
            }

            return $purchase->load('items');
        });
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Saves a bill: the sale, its lines, stock movements and any credit balance,
 * all inside one database transaction. Prices are always read from the
 * database, never trusted from the screen.
 *
 * Stock is allowed to go below zero so billing never blocks at the counter;
 * negative stock shows up in the low-stock report for the owner to correct.
 */
class CheckoutService
{
    /**
     * @param  array<int, array{product_id:int, qty:float|int|string, discount?:float|int|string}>  $lines
     */
    public function complete(
        array $lines,
        float $paid,
        string $method = 'cash',
        ?int $customerId = null,
        float $billDiscount = 0,
        ?int $userId = null,
    ): Sale {
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['qty'] ?? 0) > 0));

        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'The bill is empty.']);
        }

        if (! in_array($method, ['cash', 'card', 'wallet', 'credit'], true)) {
            throw ValidationException::withMessages(['method' => 'Unknown payment method.']);
        }

        $userId ??= auth()->id();

        return DB::transaction(function () use ($lines, $paid, $method, $customerId, $billDiscount, $userId) {
            $products = Product::whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');

            $subtotal = 0.0;
            $rows = [];

            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages(['cart' => 'A product on the bill is no longer available.']);
                }

                $qty = round((float) $line['qty'], 3);
                $lineDiscount = round((float) ($line['discount'] ?? 0), 2);
                $lineTotal = round((float) $product->sale_price * $qty - $lineDiscount, 2);

                $subtotal += $lineTotal;
                $rows[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'qty' => $qty,
                    'sale_price' => $product->sale_price,
                    'cost_price' => $product->cost_price,
                    'discount' => $lineDiscount,
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $billDiscount = round(min(max($billDiscount, 0), $subtotal), 2);
            $total = round($subtotal - $billDiscount, 2);
            $paid = round(max($paid, 0), 2);
            $due = round(max(0, $total - $paid), 2);

            if ($due > 0 && ! $customerId) {
                throw ValidationException::withMessages(['paid' => 'Paid amount is less than the total. Select a customer to sell on credit.']);
            }

            $sale = Sale::create([
                'invoice_no' => 'TMP-'.Str::uuid(),
                'customer_id' => $customerId,
                'user_id' => $userId,
                'subtotal' => $subtotal,
                'discount' => $billDiscount,
                'total' => $total,
                'paid' => min($paid, $total),
                'change_due' => round(max(0, $paid - $total), 2),
                'due' => $due,
                'payment_method' => $due > 0 ? 'credit' : $method,
            ]);

            // Invoice number from the row id: unique and gap-free per sale.
            $sale->update(['invoice_no' => 'INV-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($rows as $row) {
                $sale->items()->create($row);

                Product::whereKey($row['product_id'])->decrement('stock', $row['qty']);

                StockMovement::create([
                    'product_id' => $row['product_id'],
                    'type' => 'sale',
                    'qty' => -$row['qty'],
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'user_id' => $userId,
                ]);
            }

            if ($due > 0) {
                Customer::whereKey($customerId)->increment('balance', $due);
            }

            return $sale->load('items');
        });
    }

    /**
     * Return part or all of one bill line. Stock comes back, and the refund is
     * taken off the customer's credit first, then paid out in cash.
     *
     * @return float the amount to refund in cash
     */
    public function returnItem(SaleItem $item, float $qty, ?int $userId = null): float
    {
        $userId ??= auth()->id();

        return DB::transaction(function () use ($item, $qty, $userId) {
            $item->refresh();
            $qty = round($qty, 3);
            $returnable = round((float) $item->qty - (float) $item->returned_qty, 3);

            if ($qty <= 0 || $qty > $returnable) {
                throw ValidationException::withMessages(['qty' => "You can return at most {$returnable}."]);
            }

            $sale = $item->sale()->lockForUpdate()->first();

            // Refund at the price actually charged for this line, including its discount
            // and its share of any bill discount.
            $unitPaid = (float) $item->line_total / (float) $item->qty;
            $billShare = (float) $sale->subtotal > 0 ? (float) $sale->total / (float) $sale->subtotal : 1;
            $refund = round($unitPaid * $qty * $billShare, 2);

            $item->increment('returned_qty', $qty);
            Product::whereKey($item->product_id)->increment('stock', $qty);

            // Reduce the customer's open credit before giving cash back.
            $creditCut = 0.0;
            if ($sale->customer_id && (float) $sale->due > 0) {
                $creditCut = round(min($refund, (float) $sale->due), 2);
                $sale->decrement('due', $creditCut);
                Customer::whereKey($sale->customer_id)->decrement('balance', $creditCut);
            }

            $cashRefund = round($refund - $creditCut, 2);

            StockMovement::create([
                'product_id' => $item->product_id,
                'type' => 'return',
                'qty' => $qty,
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'cash_refund' => $cashRefund,
                'user_id' => $userId,
            ]);

            $allReturned = $sale->items()->whereColumn('returned_qty', '<', 'qty')->doesntExist();
            if ($allReturned) {
                $sale->update(['status' => 'returned']);
            }

            return $cashRefund;
        });
    }
}

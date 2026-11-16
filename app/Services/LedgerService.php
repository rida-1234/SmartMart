<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money in and out outside a bill (credit received, supplier paid),
 * plus manual stock adjustments after a physical count.
 */
class LedgerService
{
    public function receiveFromCustomer(Customer $customer, float $amount, string $method = 'cash', ?string $note = null): Payment
    {
        return $this->payment($customer, $amount, $method, $note);
    }

    public function payToSupplier(Supplier $supplier, float $amount, string $method = 'cash', ?string $note = null): Payment
    {
        return $this->payment($supplier, $amount, $method, $note);
    }

    public function adjustStock(Product $product, float $newStock, ?string $note = null): ?StockMovement
    {
        return DB::transaction(function () use ($product, $newStock, $note) {
            $product->refresh();
            $diff = round($newStock - (float) $product->stock, 3);

            if ($diff == 0.0) {
                return null;
            }

            $product->update(['stock' => round($newStock, 3)]);

            return StockMovement::create([
                'product_id' => $product->id,
                'type' => 'adjustment',
                'qty' => $diff,
                'note' => $note,
                'user_id' => auth()->id(),
            ]);
        });
    }

    private function payment(Customer|Supplier $party, float $amount, string $method, ?string $note): Payment
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be more than zero.']);
        }

        return DB::transaction(function () use ($party, $amount, $method, $note) {
            $party->decrement('balance', $amount);

            return $party->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'note' => $note,
                'user_id' => auth()->id(),
            ]);
        });
    }
}

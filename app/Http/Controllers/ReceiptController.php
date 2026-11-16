<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Setting;

class ReceiptController extends Controller
{
    public function show(Sale $sale)
    {
        $sale->load(['items', 'customer', 'user']);

        return view('receipts.show', [
            'sale' => $sale,
            'shop' => [
                'name' => Setting::get('shop_name', config('app.name')),
                'address' => Setting::get('shop_address'),
                'phone' => Setting::get('shop_phone'),
                'footer' => Setting::get('receipt_footer', 'Thank you for shopping with us!'),
                'currency' => Setting::get('currency', ''),
            ],
            'autoPrint' => request()->boolean('print'),
        ]);
    }
}

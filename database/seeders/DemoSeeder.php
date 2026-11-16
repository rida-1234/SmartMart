<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Database\Seeder;

/**
 * Practice data for learning and testing on a developer machine.
 * NOT shipped to the shop. Run: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $admin = User::where('role', User::ROLE_ADMIN)->first();
        auth()->login($admin); // stock movements record who made them

        User::firstOrCreate(
            ['email' => 'cashier@smartmart.local'],
            ['name' => 'Cashier One', 'password' => 'cashier123', 'role' => User::ROLE_CASHIER],
        );

        $pcs = Unit::where('short_name', 'pcs')->value('id');
        $kg = Unit::where('short_name', 'kg')->value('id');
        $cat = fn (string $name) => Category::firstOrCreate(['name' => $name])->id;

        $products = [
            // name, barcode, category, unit, cost, sale, stock, low level
            ['Basmati Rice 1kg', '8900000000011', 'Grocery', $pcs, 80, 100, 40, 10],
            ['Sugar (loose)', null, 'Grocery', $kg, 95, 110, 25, 5],
            ['Cooking Oil 1L', '8900000000028', 'Grocery', $pcs, 250, 300, 12, 4],
            ['Tea 250g', '8900000000035', 'Beverages', $pcs, 180, 220, 20, 5],
            ['Cola 1.5L', '8900000000042', 'Beverages', $pcs, 110, 140, 30, 6],
            ['Fresh Milk 1L', '8900000000059', 'Dairy', $pcs, 40, 50, 3, 5],
            ['Yogurt 500g', '8900000000066', 'Dairy', $pcs, 70, 90, 8, 4],
            ['Potato Chips', '8900000000073', 'Snacks', $pcs, 25, 35, 50, 10],
            ['Biscuits Pack', '8900000000080', 'Snacks', $pcs, 30, 40, 2, 10],
            ['Soap Bar', '8900000000097', 'Personal care', $pcs, 40, 60, 30, 8],
            ['Shampoo 200ml', '8900000000103', 'Personal care', $pcs, 220, 280, 10, 3],
            ['Dish Wash Liquid', '8900000000110', 'Household', $pcs, 150, 190, 9, 3],
        ];

        foreach ($products as [$name, $barcode, $category, $unit, $cost, $sale, $stock, $low]) {
            $product = Product::firstOrCreate(['name' => $name], [
                'barcode' => $barcode, 'category_id' => $cat($category), 'unit_id' => $unit,
                'cost_price' => $cost, 'sale_price' => $sale, 'stock' => 0, 'low_stock_level' => $low,
            ]);

            if ($product->wasRecentlyCreated) {
                app(LedgerService::class)->adjustStock($product, $stock, 'Opening stock');
            }
        }

        Customer::firstOrCreate(['name' => 'Ahmed (regular)'], ['phone' => '0300-0000001']);
        Customer::firstOrCreate(['name' => 'Sara (regular)'], ['phone' => '0300-0000002']);
        Supplier::firstOrCreate(['name' => 'City Wholesale'], ['phone' => '0300-0000003']);

        auth()->logout();
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Starter data shipped in the installer's database.
 * The owner must change the admin password on first login.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@smartmart.local'],
            ['name' => 'Owner', 'password' => 'admin123', 'role' => User::ROLE_ADMIN, 'is_active' => true],
        );

        foreach ([['Piece', 'pcs'], ['Kilogram', 'kg'], ['Gram', 'g'], ['Litre', 'L'], ['Pack', 'pack'], ['Dozen', 'dz']] as [$name, $short]) {
            Unit::firstOrCreate(['short_name' => $short], ['name' => $name]);
        }

        foreach (['Grocery', 'Beverages', 'Dairy', 'Snacks', 'Personal care', 'Household'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        foreach ([
            'shop_name' => 'Smart Mart',
            'shop_address' => '',
            'shop_phone' => '',
            'receipt_footer' => 'Thank you for shopping with us!',
            'currency' => '', // e.g. Rs, $, AED: set in Settings
            'backup_path' => '',
        ] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}

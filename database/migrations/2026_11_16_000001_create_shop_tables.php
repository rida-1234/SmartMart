<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('short_name', 10);
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('barcode')->nullable()->unique();
            $t->decimal('cost_price', 12, 2)->default(0);
            $t->decimal('sale_price', 12, 2)->default(0);
            $t->decimal('stock', 12, 3)->default(0);
            $t->decimal('low_stock_level', 12, 3)->default(5);
            $t->date('expiry_date')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index('name');
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('address')->nullable();
            $t->decimal('balance', 12, 2)->default(0); // what the shop owes the supplier
            $t->timestamps();
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('address')->nullable();
            $t->decimal('balance', 12, 2)->default(0); // credit (udhaar) the customer owes the shop
            $t->timestamps();
        });

        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('invoice_no')->nullable(); // supplier's bill number
            $t->date('date');
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->decimal('paid', 12, 2)->default(0);
            $t->decimal('due', 12, 2)->default(0);
            $t->text('note')->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->decimal('qty', 12, 3);
            $t->decimal('cost_price', 12, 2);
            $t->decimal('line_total', 12, 2);
            $t->timestamps();
        });

        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_no')->unique();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('subtotal', 12, 2);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->decimal('paid', 12, 2);
            $t->decimal('change_due', 12, 2)->default(0);
            $t->decimal('due', 12, 2)->default(0);
            $t->string('payment_method')->default('cash'); // cash | card | wallet | credit
            $t->string('status')->default('completed');     // completed | returned
            $t->timestamps();
            $t->index('created_at');
        });

        Schema::create('sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->string('name');
            $t->decimal('qty', 12, 3);
            $t->decimal('returned_qty', 12, 3)->default(0);
            $t->decimal('sale_price', 12, 2);
            $t->decimal('cost_price', 12, 2);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2);
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->morphs('payable'); // Customer (received) or Supplier (paid out)
            $t->decimal('amount', 12, 2);
            $t->string('method')->default('cash');
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('category');
            $t->decimal('amount', 12, 2);
            $t->date('date');
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->string('type'); // purchase | sale | return | adjustment
            $t->decimal('qty', 12, 3); // + in, - out
            $t->nullableMorphs('reference');
            $t->decimal('cash_refund', 12, 2)->default(0); // cash given back on a return
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'stock_movements', 'expenses', 'payments', 'sale_items', 'sales',
            'purchase_items', 'purchases', 'customers', 'suppliers', 'products', 'units', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\LedgerService;
use App\Services\PurchaseService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopFlowTest extends TestCase
{
    use RefreshDatabase;

    private Product $rice;

    private Product $milk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->rice = Product::create(['name' => 'Rice 1kg', 'barcode' => '111', 'cost_price' => 80, 'sale_price' => 100, 'stock' => 10]);
        $this->milk = Product::create(['name' => 'Milk 1L', 'barcode' => '222', 'cost_price' => 40, 'sale_price' => 50, 'stock' => 20]);
    }

    public function test_cash_sale_saves_bill_reduces_stock_and_logs_movements(): void
    {
        $sale = app(CheckoutService::class)->complete([
            ['product_id' => $this->rice->id, 'qty' => 2],
            ['product_id' => $this->milk->id, 'qty' => 3],
        ], paid: 500);

        $this->assertSame('INV-000001', $sale->invoice_no);
        $this->assertEquals(350, $sale->total);
        $this->assertEquals(150, $sale->change_due);
        $this->assertEquals(8, $this->rice->fresh()->stock);
        $this->assertEquals(17, $this->milk->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('sale_items', ['name' => 'Rice 1kg', 'cost_price' => 80]);
    }

    public function test_price_comes_from_database_not_from_screen(): void
    {
        $sale = app(CheckoutService::class)->complete([
            ['product_id' => $this->rice->id, 'qty' => 1, 'sale_price' => 1],
        ], paid: 100);

        $this->assertEquals(100, $sale->total);
    }

    public function test_short_payment_needs_a_customer_and_becomes_credit(): void
    {
        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->complete([['product_id' => $this->rice->id, 'qty' => 1]], paid: 40);
    }

    public function test_credit_sale_increases_customer_balance(): void
    {
        $customer = Customer::create(['name' => 'Ali']);

        $sale = app(CheckoutService::class)->complete(
            [['product_id' => $this->rice->id, 'qty' => 1]], paid: 40, customerId: $customer->id,
        );

        $this->assertSame('credit', $sale->payment_method);
        $this->assertEquals(60, $sale->due);
        $this->assertEquals(60, $customer->fresh()->balance);

        app(LedgerService::class)->receiveFromCustomer($customer->fresh(), 60);
        $this->assertEquals(0, $customer->fresh()->balance);
    }

    public function test_return_restores_stock_and_refunds_cash(): void
    {
        $checkout = app(CheckoutService::class);
        $sale = $checkout->complete([['product_id' => $this->rice->id, 'qty' => 2]], paid: 200);

        $refund = $checkout->returnItem($sale->items->first(), 1);

        $this->assertEquals(100, $refund);
        $this->assertEquals(9, $this->rice->fresh()->stock);
        $this->assertSame('completed', $sale->fresh()->status);

        $checkout->returnItem($sale->items->first(), 1);
        $this->assertSame('returned', $sale->fresh()->status);

        $this->expectException(ValidationException::class);
        $checkout->returnItem($sale->items->first(), 1);
    }

    public function test_purchase_adds_stock_updates_cost_and_supplier_balance(): void
    {
        $supplier = Supplier::create(['name' => 'Wholesale Co']);

        app(PurchaseService::class)->record(
            ['supplier_id' => $supplier->id, 'date' => now()->toDateString(), 'paid' => 500],
            [['product_id' => $this->rice->id, 'qty' => 10, 'cost_price' => 85]],
        );

        $this->assertEquals(20, $this->rice->fresh()->stock);
        $this->assertEquals(85, $this->rice->fresh()->cost_price);
        $this->assertEquals(350, $supplier->fresh()->balance);
    }

    public function test_daily_report_profit_and_expected_cash(): void
    {
        $checkout = app(CheckoutService::class);
        $sale = $checkout->complete([['product_id' => $this->rice->id, 'qty' => 2]], paid: 200);
        $checkout->complete([['product_id' => $this->milk->id, 'qty' => 2]], paid: 100);
        $checkout->returnItem($sale->items->first(), 1);

        $reports = app(ReportService::class);
        $s = $reports->summary(today(), today());

        $this->assertSame(2, $s['bills']);
        $this->assertEquals(200, $s['net_sales']);     // 300 sold - 100 returned
        $this->assertEquals(160, $s['cost_of_goods']); // rice 1 kept x 80 + milk 2 x 40
        $this->assertEquals(40, $s['gross_profit']);
        $this->assertEquals(200, $reports->expectedCash(today()));
    }

    public function test_stock_adjustment_logs_the_difference(): void
    {
        $movement = app(LedgerService::class)->adjustStock($this->rice, 7, 'Physical count');

        $this->assertEquals(-3, $movement->qty);
        $this->assertEquals(7, $this->rice->fresh()->stock);
    }
}

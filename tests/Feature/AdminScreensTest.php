<?php

namespace Tests\Feature;

use App\Filament\Pages\Pos;
use App\Filament\Pages\Reports;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Sheets\ProductSheet;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
    }

    public function test_admin_can_open_every_screen(): void
    {
        $this->actingAs($this->admin);
        $sale = $this->makeSale();

        foreach ([
            '/admin', '/admin/pos', '/admin/products', '/admin/products/create', '/admin/categories',
            '/admin/units', '/admin/suppliers', '/admin/customers', '/admin/purchases', '/admin/purchases/create',
            '/admin/sales', "/admin/sales/{$sale->id}", '/admin/expenses', '/admin/reports', '/admin/settings',
            '/admin/users', "/receipts/{$sale->id}",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_cashier_only_sees_billing_customers_and_own_sales(): void
    {
        $this->actingAs($this->cashier);

        foreach (['/admin/pos', '/admin/customers', '/admin/sales'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/admin/products', '/admin/purchases', '/admin/reports', '/admin/settings', '/admin/users', '/admin/expenses'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_login_opens_the_billing_screen(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['email' => $this->cashier->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect('/admin/pos');
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/admin/pos')->assertRedirect('/admin/login');
        $this->get('/')->assertRedirect('/admin/pos');
    }

    public function test_pos_scan_pay_and_print(): void
    {
        $this->actingAs($this->cashier);
        $rice = Product::create(['name' => 'Rice 1kg', 'barcode' => '111', 'cost_price' => 80, 'sale_price' => 100, 'stock' => 10]);

        Livewire::test(Pos::class)
            ->set('scan', '111')->call('addByScan')
            ->set('scan', '111')->call('addByScan')
            ->assertSet("cart.{$rice->id}.qty", 2)
            ->set('paid', '500')
            ->call('pay')
            ->assertHasNoErrors()
            ->assertSet('cart', [])
            ->assertDispatched('pos-print');

        $sale = Sale::first();
        $this->assertEquals(200, $sale->total);
        $this->assertEquals(300, $sale->change_due);
        $this->assertSame($this->cashier->id, $sale->user_id);
        $this->assertEquals(8, $rice->fresh()->stock);
    }

    public function test_pos_search_by_name_hold_and_resume(): void
    {
        $this->actingAs($this->cashier);
        Product::create(['name' => 'Milk 1L', 'barcode' => '222', 'sale_price' => 50, 'stock' => 5]);

        Livewire::test(Pos::class)
            ->set('scan', 'milk')->call('addByScan')
            ->assertCount('cart', 1)
            ->call('hold')
            ->assertSet('cart', [])
            ->call('resume', 0)
            ->assertCount('cart', 1);
    }

    public function test_pos_credit_needs_a_customer(): void
    {
        $this->actingAs($this->cashier);
        $p = Product::create(['name' => 'Oil', 'barcode' => '333', 'sale_price' => 300, 'stock' => 5]);
        $customer = Customer::create(['name' => 'Ahmed']);

        Livewire::test(Pos::class)
            ->call('addProduct', $p->id)
            ->set('method', 'credit')
            ->call('pay')
            ->assertHasErrors('paid')
            ->set('customerId', $customer->id)
            ->call('pay')
            ->assertHasNoErrors();

        $this->assertEquals(300, $customer->fresh()->balance);
    }

    public function test_create_product_records_opening_stock_movement(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Sugar 1kg', 'barcode' => '444', 'cost_price' => 90, 'sale_price' => 110, 'stock' => 25, 'low_stock_level' => 5, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('barcode', '444')->first();
        $this->assertEquals(25, $product->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'adjustment', 'note' => 'Opening stock']);
    }

    public function test_create_purchase_through_the_form(): void
    {
        $this->actingAs($this->admin);
        $supplier = Supplier::create(['name' => 'Wholesale Co']);
        $p = Product::create(['name' => 'Tea', 'cost_price' => 100, 'sale_price' => 150, 'stock' => 0]);

        Livewire::test(CreatePurchase::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'date' => now()->toDateString(),
                'items' => [['product_id' => $p->id, 'qty' => 12, 'cost_price' => 95]],
                'discount' => 0,
                'paid' => 1000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals(12, $p->fresh()->stock);
        $this->assertEquals(95, $p->fresh()->cost_price);
        $this->assertEquals(140, $supplier->fresh()->balance);
    }

    public function test_return_from_bill_screen(): void
    {
        $this->actingAs($this->admin);
        $sale = $this->makeSale();
        $item = $sale->items->first();

        Livewire::test(ViewSale::class, ['record' => $sale->getRouteKey()])
            ->callAction('return', ['sale_item_id' => $item->id, 'qty' => 1])
            ->assertHasNoActionErrors();

        $this->assertEquals(1, $item->fresh()->returned_qty);
    }

    public function test_reports_and_settings_pages(): void
    {
        $this->actingAs($this->admin);
        $this->makeSale();

        Livewire::test(Reports::class)
            ->call('setRange', 'month')
            ->assertSee('Top selling products')
            ->set('countedCash', '200')
            ->assertSee('Cash matches.');

        Livewire::test(Settings::class)
            ->fillForm(['shop_name' => 'Test Mart', 'currency' => 'Rs'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Test Mart', Setting::get('shop_name'));
    }

    public function test_csv_import_creates_and_updates(): void
    {
        $this->actingAs($this->admin);
        Product::create(['name' => 'Rice 1kg', 'barcode' => '111', 'sale_price' => 90, 'stock' => 3]);

        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "\xEF\xBB\xBFName,Barcode,Category,Unit,Cost Price,Sale Price,Stock\n"
            ."Rice 1kg,111,Grocery,pcs,80,100,10\n"
            ."Soap,555,Personal care,pcs,40,60,24\n"
            .",999,,,1,2,3\n");

        $result = app(ProductSheet::class)->import($csv);
        unlink($csv);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals(100, Product::where('barcode', '111')->value('sale_price'));
        $this->assertEquals(10, Product::where('barcode', '111')->value('stock'));
        $this->assertEquals(24, Product::where('barcode', '555')->value('stock'));
    }

    private function makeSale(): Sale
    {
        $p = Product::create(['name' => 'Rice 1kg', 'barcode' => 'X1', 'cost_price' => 80, 'sale_price' => 100, 'stock' => 10]);

        return app(CheckoutService::class)->complete([['product_id' => $p->id, 'qty' => 2]], paid: 200);
    }
}

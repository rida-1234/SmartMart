<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Sheets\ProductSheet;
use App\Services\Sheets\SupplierSheet;
use App\Support\Spreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    private array $tmp = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    }

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), $this->tmp);
        parent::tearDown();
    }

    public function test_suppliers_import_from_excel_with_friendly_headers(): void
    {
        $file = $this->xlsx([
            ['Supplier Name', 'Mobile', 'Address', 'Opening Balance'],
            ['City Wholesale', '0300-1111111', 'Main Market', 1500],
            ['Fresh Dairy Co', 3001234567, null, null],
            [null, '0300-0000000', null, null],
        ]);

        $result = app(SupplierSheet::class)->import($file);

        $this->assertSame(2, $result['created']);
        $this->assertCount(1, $result['errors']); // the row without a name
        $this->assertEquals(1500, Supplier::where('name', 'City Wholesale')->value('balance'));
        $this->assertSame('3001234567', Supplier::where('name', 'Fresh Dairy Co')->value('phone'));
    }

    public function test_supplier_import_never_changes_an_existing_balance(): void
    {
        Supplier::create(['name' => 'City Wholesale', 'balance' => 700]);

        $result = app(SupplierSheet::class)->import($this->csv("name,phone,balance\ncity wholesale,0300-2222222,0\n"));

        $this->assertSame(1, $result['updated']);
        $this->assertCount(1, $result['warnings']);
        $supplier = Supplier::first();
        $this->assertEquals(700, $supplier->balance);
        $this->assertSame('0300-2222222', $supplier->phone);
    }

    public function test_products_import_from_excel_keeps_long_barcodes_and_dates(): void
    {
        $file = $this->xlsx([
            ['Product', 'Barcode', 'Category', 'Unit', 'Cost', 'Price', 'Qty', 'Expiry', 'Active'],
            ['Basmati Rice 1kg', 8900000000011.0, 'Grocery', 'pcs', 80, 100, 40, new \DateTimeImmutable('2027-03-31'), 'yes'],
            ['Old Biscuits', '8900000000080', 'Snacks', 'pcs', 30, 40, 0, '31/12/2026', 'no'],
        ]);

        $result = app(ProductSheet::class)->import($file);

        $this->assertSame(2, $result['created'], json_encode($result));
        $rice = Product::where('barcode', '8900000000011')->first();
        $this->assertNotNull($rice);
        $this->assertEquals(40, $rice->stock);
        $this->assertSame('2027-03-31', $rice->expiry_date->toDateString());
        $this->assertDatabaseHas('stock_movements', ['product_id' => $rice->id, 'qty' => 40, 'note' => 'Imported from file']);
        $this->assertFalse(Product::where('name', 'Old Biscuits')->value('is_active'));
        $this->assertSame('2026-12-31', Product::where('name', 'Old Biscuits')->first()->expiry_date->toDateString());
    }

    public function test_semicolon_csv_and_bad_values_are_reported_per_row(): void
    {
        $result = app(ProductSheet::class)->import($this->csv("name;sale_price;stock\nTea;220;10\nSoap;abc;5\n"));

        $this->assertSame(1, $result['created']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Row 3', $result['errors'][0]);
        $this->assertNull(Product::where('name', 'Soap')->first(), 'a failed row must not be half-saved');
    }

    public function test_export_then_import_round_trip_changes_nothing(): void
    {
        $p = Product::create(['name' => 'Tea 250g', 'barcode' => '8900000000035', 'cost_price' => 180, 'sale_price' => 220, 'stock' => 20]);

        foreach (['xlsx', 'csv'] as $format) {
            $file = $this->tmpFile($format);
            $sheet = app(ProductSheet::class);
            Spreadsheet::write($file, $format, $sheet->columns(), $sheet->exportRows($sheet->query()));

            $result = $sheet->import($file);

            $this->assertSame(['created' => 0, 'updated' => 1, 'errors' => [], 'warnings' => []], $result, $format);
            $this->assertEquals(20, $p->fresh()->stock);
            $this->assertDatabaseCount('stock_movements', 0); // same stock = no adjustment
        }
    }

    public function test_export_and_template_buttons_download_files(): void
    {
        Product::create(['name' => 'Tea', 'sale_price' => 220, 'stock' => 3, 'low_stock_level' => 5]);
        Supplier::create(['name' => 'City Wholesale']);

        Livewire::test(ListProducts::class)
            ->callAction('export', ['format' => 'xlsx', 'filtered' => true])
            ->assertFileDownloaded();

        Livewire::test(ListSuppliers::class)
            ->callAction('export', ['format' => 'csv', 'filtered' => false])
            ->assertFileDownloaded();

        Livewire::test(ListSuppliers::class)
            ->callAction('template')
            ->assertFileDownloaded('suppliers-template.xlsx');
    }

    private function xlsx(array $rows): string
    {
        $file = $this->tmpFile('xlsx');
        $writer = new XlsxWriter;
        $writer->openToFile($file);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $file;
    }

    private function csv(string $content): string
    {
        $file = $this->tmpFile('csv');
        file_put_contents($file, $content);

        return $file;
    }

    private function tmpFile(string $ext): string
    {
        return $this->tmp[] = sys_get_temp_dir().'/sheet-'.uniqid().'.'.$ext;
    }
}

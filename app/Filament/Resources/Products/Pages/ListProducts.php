<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\ProductImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalDescription('In Excel: File > Save As > CSV. Columns: name, barcode, category, unit, cost_price, sale_price, stock, low_stock_level. Existing products (same barcode, or same name) are updated.')
                ->schema([
                    FileUpload::make('file')
                        ->label('CSV file')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);
                    $result = app(ProductImportService::class)->import($path);
                    Storage::disk('local')->delete($data['file']);

                    Notification::make()
                        ->title("Imported: {$result['created']} new, {$result['updated']} updated")
                        ->body($result['errors'] ? 'Skipped rows: '.implode('; ', array_slice($result['errors'], 0, 5)) : null)
                        ->{$result['errors'] ? 'warning' : 'success'}()
                        ->persistent()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}

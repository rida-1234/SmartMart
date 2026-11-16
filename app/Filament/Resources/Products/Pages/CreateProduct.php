<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\LedgerService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** Opening stock is recorded as a stock movement, so every unit can be traced. */
    protected function handleRecordCreation(array $data): Model
    {
        $opening = (float) ($data['stock'] ?? 0);
        $data['stock'] = 0;

        return DB::transaction(function () use ($data, $opening) {
            $product = Product::create($data);

            if ($opening != 0) {
                app(LedgerService::class)->adjustStock($product, $opening, 'Opening stock');
            }

            return $product->refresh();
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

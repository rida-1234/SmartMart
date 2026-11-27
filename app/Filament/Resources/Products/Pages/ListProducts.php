<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Actions\SheetActions;
use App\Filament\Resources\Products\ProductResource;
use App\Services\Sheets\ProductSheet;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SheetActions::group(ProductSheet::class),
            CreateAction::make(),
        ];
    }
}

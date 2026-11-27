<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Actions\SheetActions;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Services\Sheets\SupplierSheet;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SheetActions::group(SupplierSheet::class),
            CreateAction::make(),
        ];
    }
}

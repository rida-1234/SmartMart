<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(32),
                TextInput::make('address')->maxLength(255)->columnSpanFull(),
                TextInput::make('balance')
                    ->label('Opening balance (we owe them)')
                    ->numeric()
                    ->default(0)
                    ->visibleOn('create'),
            ]);
    }
}

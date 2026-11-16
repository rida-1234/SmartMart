<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
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
                    ->label('Opening credit (they owe the shop)')
                    ->numeric()
                    ->default(0)
                    ->visibleOn('create'),
            ]);
    }
}

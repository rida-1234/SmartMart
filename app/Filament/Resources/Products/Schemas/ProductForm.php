<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->autofocus(),
                        TextInput::make('barcode')
                            ->helperText('Scan the product barcode here. Leave empty for loose items.')
                            ->unique(ignoreRecord: true)
                            ->maxLength(64),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([TextInput::make('name')->required()]),
                        Select::make('unit_id')
                            ->relationship('unit', 'name')
                            ->preload(),
                    ]),
                Section::make('Price')
                    ->columns(2)
                    ->schema([
                        TextInput::make('cost_price')
                            ->label('Cost price (buying)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('sale_price')
                            ->label('Sale price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),
                Section::make('Stock')
                    ->columns(2)
                    ->schema([
                        TextInput::make('stock')
                            ->label('Opening stock')
                            ->helperText('After saving, stock changes only through purchases, sales, returns or "Adjust stock".')
                            ->numeric()
                            ->default(0)
                            ->visibleOn('create'),
                        TextInput::make('low_stock_level')
                            ->label('Alert when stock is at or below')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(5),
                        DatePicker::make('expiry_date'),
                        Toggle::make('is_active')
                            ->label('Active (can be sold)')
                            ->default(true),
                    ]),
            ]);
    }
}

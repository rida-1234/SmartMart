<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Supplier bill')
                    ->columns(3)
                    ->schema([
                        Select::make('supplier_id')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')->required(),
                                TextInput::make('phone'),
                            ]),
                        DatePicker::make('date')->required()->default(now()),
                        TextInput::make('invoice_no')->label('Supplier bill no.'),
                    ]),
                Repeater::make('items')
                    ->label('Items received')
                    ->required()
                    ->minItems(1)
                    ->defaultItems(1)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Product::query()
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('barcode', $search)
                                ->limit(30)
                                ->pluck('name', 'id'))
                            ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $set('cost_price', Product::find($state)?->cost_price)),
                        TextInput::make('qty')->label('Quantity')->numeric()->minValue(0.001)->default(1)->required(),
                        TextInput::make('cost_price')->label('Cost price (each)')->numeric()->minValue(0)->required(),
                    ]),
                Section::make('Payment')
                    ->columns(3)
                    ->schema([
                        TextInput::make('discount')->numeric()->minValue(0)->default(0),
                        TextInput::make('paid')->label('Paid now')->numeric()->minValue(0)->default(0)
                            ->helperText('Any unpaid amount is added to the supplier balance.'),
                        TextInput::make('note'),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Purchases;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\Pages\ViewPurchase;
use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Filament\Resources\Purchases\Tables\PurchasesTable;
use App\Models\Purchase;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PurchaseResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Purchase::class;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    public static function form(Schema $schema): Schema
    {
        return PurchaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('supplier.name'),
                TextEntry::make('date')->date(),
                TextEntry::make('invoice_no')->label('Supplier bill no.')->placeholder('-'),
                TextEntry::make('subtotal')->numeric(decimalPlaces: 2),
                TextEntry::make('discount')->numeric(decimalPlaces: 2),
                TextEntry::make('total')->numeric(decimalPlaces: 2)->weight('bold'),
                TextEntry::make('paid')->numeric(decimalPlaces: 2),
                TextEntry::make('due')->numeric(decimalPlaces: 2)->color('danger'),
                TextEntry::make('user.name')->label('Entered by'),
                TextEntry::make('note')->placeholder('-')->columnSpanFull(),
            ]),
            RepeatableEntry::make('items')->columnSpanFull()->columns(4)->schema([
                TextEntry::make('product.name'),
                TextEntry::make('qty')->numeric(),
                TextEntry::make('cost_price')->numeric(decimalPlaces: 2),
                TextEntry::make('line_total')->numeric(decimalPlaces: 2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return PurchasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
            'view' => ViewPurchase::route('/{record}'),
        ];
    }
}

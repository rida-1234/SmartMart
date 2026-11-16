<?php

namespace App\Filament\Resources\Sales\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('invoice_no')->label('Bill no.')->weight('bold'),
                TextEntry::make('created_at')->label('Date')->dateTime(),
                TextEntry::make('user.name')->label('Cashier'),
                TextEntry::make('customer.name')->placeholder('Walk-in'),
                TextEntry::make('subtotal')->numeric(decimalPlaces: 2),
                TextEntry::make('discount')->numeric(decimalPlaces: 2),
                TextEntry::make('total')->numeric(decimalPlaces: 2)->weight('bold'),
                TextEntry::make('payment_method')->badge(),
                TextEntry::make('paid')->numeric(decimalPlaces: 2),
                TextEntry::make('change_due')->label('Change given')->numeric(decimalPlaces: 2),
                TextEntry::make('due')->label('Credit due')->numeric(decimalPlaces: 2),
                TextEntry::make('status')->badge()->color(fn (string $state) => $state === 'returned' ? 'danger' : 'success'),
            ]),
            RepeatableEntry::make('items')->columnSpanFull()->columns(5)->schema([
                TextEntry::make('name'),
                TextEntry::make('qty')->numeric(),
                TextEntry::make('returned_qty')->label('Returned')->numeric(),
                TextEntry::make('sale_price')->label('Price')->numeric(decimalPlaces: 2),
                TextEntry::make('line_total')->label('Total')->numeric(decimalPlaces: 2),
            ]),
        ]);
    }
}

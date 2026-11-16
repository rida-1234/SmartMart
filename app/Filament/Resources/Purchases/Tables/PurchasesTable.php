<?php

namespace App\Filament\Resources\Purchases\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('supplier.name')->searchable(),
                TextColumn::make('invoice_no')->label('Bill no.')->searchable(),
                TextColumn::make('total')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('paid')->numeric(decimalPlaces: 2),
                TextColumn::make('due')->numeric(decimalPlaces: 2)->color(fn ($state) => $state > 0 ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('supplier')->relationship('supplier', 'name'),
            ])
            ->recordActions([ViewAction::make()]);
    }
}

<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Services\LedgerService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $record) => $record->barcode),
                TextColumn::make('barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('cost_price')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sale_price')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('stock')
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->suffix(fn (Product $record) => $record->unit ? ' '.$record->unit->short_name : '')
                    ->color(fn (Product $record) => $record->stock <= $record->low_stock_level ? 'danger' : null)
                    ->weight(fn (Product $record) => $record->stock <= $record->low_stock_level ? 'bold' : null),
                TextColumn::make('expiry_date')
                    ->date()
                    ->sortable()
                    ->color(fn (Product $record) => $record->expiry_date && $record->expiry_date->lte(now()->addDays(30)) ? 'warning' : null)
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Filter::make('low_stock')
                    ->label('Low stock')
                    ->query(fn (Builder $query) => $query->lowStock()),
                Filter::make('expiring')
                    ->label('Expiring in 30 days')
                    ->query(fn (Builder $query) => $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', now()->addDays(30))),
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('adjustStock')
                    ->label('Adjust stock')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('gray')
                    ->modalDescription('Enter the counted quantity. The difference is logged as an adjustment.')
                    ->fillForm(fn (Product $record) => ['stock' => (float) $record->stock])
                    ->schema([
                        TextInput::make('stock')->label('Counted stock')->numeric()->required(),
                        TextInput::make('note')->placeholder('e.g. Physical count, damaged, expired'),
                    ])
                    ->action(function (Product $record, array $data) {
                        app(LedgerService::class)->adjustStock($record, (float) $data['stock'], $data['note'] ?? null);
                        Notification::make()->title('Stock updated')->success()->send();
                    }),
            ]);
    }
}

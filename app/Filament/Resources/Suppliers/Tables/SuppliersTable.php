<?php

namespace App\Filament\Resources\Suppliers\Tables;

use App\Models\Supplier;
use App\Services\LedgerService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('balance')
                    ->label('We owe')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn (Supplier $record) => $record->balance > 0 ? 'danger' : null),
            ])
            ->filters([
                Filter::make('owed')->label('We owe money')->query(fn (Builder $q) => $q->where('balance', '>', 0)),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('Pay')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->fillForm(fn (Supplier $record) => ['amount' => max(0, (float) $record->balance), 'method' => 'cash'])
                    ->schema([
                        TextInput::make('amount')->numeric()->minValue(0.01)->required(),
                        Select::make('method')->options(['cash' => 'Cash', 'bank' => 'Bank transfer', 'wallet' => 'Mobile wallet'])->required(),
                        TextInput::make('note'),
                    ])
                    ->action(function (Supplier $record, array $data) {
                        app(LedgerService::class)->payToSupplier($record, (float) $data['amount'], $data['method'], $data['note'] ?? null);
                        Notification::make()->title('Payment recorded')->success()->send();
                    }),
                EditAction::make(),
            ]);
    }
}

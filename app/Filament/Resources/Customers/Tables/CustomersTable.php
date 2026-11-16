<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
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

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('balance')
                    ->label('Owes (credit)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn (Customer $record) => $record->balance > 0 ? 'danger' : null),
            ])
            ->filters([
                Filter::make('owed')->label('Owes the shop')->query(fn (Builder $q) => $q->where('balance', '>', 0)),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('Receive payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->fillForm(fn (Customer $record) => ['amount' => max(0, (float) $record->balance), 'method' => 'cash'])
                    ->schema([
                        TextInput::make('amount')->numeric()->minValue(0.01)->required(),
                        Select::make('method')->options(['cash' => 'Cash', 'bank' => 'Bank transfer', 'wallet' => 'Mobile wallet'])->required(),
                        TextInput::make('note'),
                    ])
                    ->action(function (Customer $record, array $data) {
                        app(LedgerService::class)->receiveFromCustomer($record, (float) $data['amount'], $data['method'], $data['note'] ?? null);
                        Notification::make()->title('Payment received')->success()->send();
                    }),
                EditAction::make(),
            ]);
    }
}

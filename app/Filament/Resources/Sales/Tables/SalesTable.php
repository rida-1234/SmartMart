<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('invoice_no')->label('Bill no.')->searchable(),
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('customer.name')->placeholder('Walk-in')->searchable(),
                TextColumn::make('user.name')->label('Cashier')->toggleable(),
                TextColumn::make('total')->numeric(decimalPlaces: 2)->sortable()
                    ->summarize(Sum::make()->numeric(decimalPlaces: 2)),
                TextColumn::make('payment_method')->label('Paid by')->badge(),
                TextColumn::make('due')->label('Credit')->numeric(decimalPlaces: 2)->toggleable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'returned' ? 'danger' : 'success'),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([DatePicker::make('from')->default(now()), DatePicker::make('until')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(fn (array $data) => ($data['from'] ?? null) ? 'From '.$data['from'] : null),
                SelectFilter::make('payment_method')->options([
                    'cash' => 'Cash', 'card' => 'Card', 'wallet' => 'Wallet', 'credit' => 'Credit',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('print')
                    ->label('Reprint')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Sale $record) => route('receipts.show', ['sale' => $record, 'print' => 1]))
                    ->openUrlInNewTab(),
            ]);
    }
}

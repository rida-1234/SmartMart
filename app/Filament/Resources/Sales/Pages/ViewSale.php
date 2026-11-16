<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\SaleItem;
use App\Services\CheckoutService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Reprint receipt')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('receipts.show', ['sale' => $this->record, 'print' => 1]))
                ->openUrlInNewTab(),
            Action::make('return')
                ->label('Return item')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => auth()->user()->isAdmin() && $this->record->status !== 'returned')
                ->schema([
                    Select::make('sale_item_id')
                        ->label('Item')
                        ->required()
                        ->options(fn () => $this->record->items
                            ->filter(fn (SaleItem $i) => $i->qty > $i->returned_qty)
                            ->mapWithKeys(fn (SaleItem $i) => [$i->id => "{$i->name} (sold ".(float) $i->qty.', returned '.(float) $i->returned_qty.')'])),
                    TextInput::make('qty')->label('Quantity to return')->numeric()->minValue(0.001)->default(1)->required(),
                ])
                ->action(function (array $data) {
                    $item = $this->record->items()->findOrFail($data['sale_item_id']);
                    $cash = app(CheckoutService::class)->returnItem($item, (float) $data['qty']);

                    Notification::make()
                        ->title('Return recorded')
                        ->body($cash > 0 ? 'Give back '.number_format($cash, 2).' in cash.' : 'Taken off the customer\'s credit.')
                        ->success()
                        ->persistent()
                        ->send();

                    $this->record->refresh();
                }),
        ];
    }
}

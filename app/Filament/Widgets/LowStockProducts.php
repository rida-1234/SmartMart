<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockProducts extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Low stock: reorder these';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query()->where('is_active', true)->lowStock()->orderBy('stock'))
            ->paginated([10])
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('stock')->numeric()->color('danger'),
                TextColumn::make('low_stock_level')->label('Alert level')->numeric(),
                TextColumn::make('cost_price')->numeric(decimalPlaces: 2),
            ]);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Services\ReportService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $s = app(ReportService::class)->summary(today(), today());
        $low = Product::where('is_active', true)->lowStock()->count();

        return [
            Stat::make('Sales today', number_format($s['net_sales'], 2))->description($s['bills'].' bills'),
            Stat::make('Profit today', number_format($s['gross_profit'], 2))->description('Before expenses'),
            Stat::make('Credit given today', number_format($s['credit_given'], 2)),
            Stat::make('Low-stock products', $low)->color($low > 0 ? 'danger' : 'success'),
        ];
    }
}

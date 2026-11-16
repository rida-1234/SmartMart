<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AdminOnly;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use UnitEnum;

/** Sales/profit summary for a date range, plus day-end cash closing. */
class Reports extends Page
{
    use AdminOnly;

    protected string $view = 'filament.pages.reports';

    protected static string|UnitEnum|null $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public string $from = '';

    public string $to = '';

    public ?string $openingCash = null;

    public ?string $countedCash = null;

    public function mount(): void
    {
        $this->from = $this->to = today()->toDateString();
    }

    public function setRange(string $range): void
    {
        [$from, $to] = match ($range) {
            'week' => [today()->startOfWeek(), today()],
            'month' => [today()->startOfMonth(), today()],
            'last-month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            default => [today(), today()],
        };
        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    private function range(): array
    {
        $from = rescue(fn () => Carbon::parse($this->from), today(), false);
        $to = rescue(fn () => Carbon::parse($this->to), today(), false);

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    #[Computed]
    public function summary(): array
    {
        return app(ReportService::class)->summary(...$this->range());
    }

    #[Computed]
    public function topProducts()
    {
        return app(ReportService::class)->topProducts(...$this->range());
    }

    #[Computed]
    public function stockValue(): array
    {
        return app(ReportService::class)->stockValue();
    }

    #[Computed]
    public function dues(): array
    {
        return app(ReportService::class)->dues();
    }

    #[Computed]
    public function expectedCash(): float
    {
        return app(ReportService::class)->expectedCash(today(), (float) $this->openingCash);
    }
}

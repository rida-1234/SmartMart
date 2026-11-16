<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AdminOnly;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class Settings extends Page
{
    use AdminOnly;

    protected string $view = 'filament.pages.settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public const KEYS = ['shop_name', 'shop_address', 'shop_phone', 'receipt_footer', 'currency', 'backup_path'];

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Shop details (printed on receipts)')
                    ->columns(2)
                    ->schema([
                        TextInput::make('shop_name')->required(),
                        TextInput::make('shop_phone'),
                        TextInput::make('shop_address')->columnSpanFull(),
                        TextInput::make('receipt_footer')->columnSpanFull(),
                        TextInput::make('currency')->placeholder('e.g. Rs, $, AED')->maxLength(8),
                    ]),
                Section::make('Backup')
                    ->schema([
                        TextInput::make('backup_path')
                            ->label('Extra backup folder')
                            ->placeholder('e.g. E:\\SmartMartBackups or a Google Drive folder')
                            ->helperText('A copy of every backup is also saved in the app\'s storage\\backups folder (last 30 days).'),
                    ]),
            ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            if (in_array($key, self::KEYS, true)) {
                Setting::put($key, $value ?? '');
            }
        }

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backup')
                ->label('Backup now')
                ->icon('heroicon-o-circle-stack')
                ->action(function () {
                    $code = Artisan::call('smartmart:backup');
                    $output = trim(Artisan::output());

                    Notification::make()
                        ->title($code === 0 ? 'Backup done' : 'Backup failed')
                        ->body($output)
                        ->{$code === 0 ? 'success' : 'danger'}()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        $last = Setting::get('last_backup_at');

        return $last ? "Last backup: {$last}" : 'No backup taken yet.';
    }
}

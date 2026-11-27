<?php

namespace App\Filament\Actions;

use App\Services\Sheets\Sheet;
use App\Support\Spreadsheet;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Import / Export" menu for a list page: Import (CSV or Excel), Export
 * (respects the current search and filters), and an empty template.
 */
class SheetActions
{
    /** @param  class-string<Sheet>  $sheetClass */
    public static function group(string $sheetClass): ActionGroup
    {
        return ActionGroup::make([
            self::import($sheetClass),
            self::export($sheetClass),
            self::template($sheetClass),
        ])
            ->label('Import / Export')
            ->icon('heroicon-o-arrows-up-down')
            ->color('gray')
            ->button();
    }

    /** @param  class-string<Sheet>  $sheetClass */
    public static function import(string $sheetClass): Action
    {
        $sheet = app($sheetClass);

        return Action::make('import')
            ->label('Import from Excel / CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Import '.$sheet->name())
            ->modalDescription('First row = column names. Columns: '.implode(', ', $sheet->columns())
                .'. Only "name" is required. Existing rows are updated, new ones are added. Tip: export first, edit the file in Excel, then import it back.')
            ->modalSubmitActionLabel('Import')
            ->schema([
                FileUpload::make('file')
                    ->label('Excel (.xlsx) or CSV file')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes(Spreadsheet::ACCEPTED_MIME_TYPES)
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (array $data) use ($sheetClass) {
                $path = Storage::disk('local')->path($data['file']);

                try {
                    $result = app($sheetClass)->import($path);
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }

                $problems = array_merge($result['errors'], $result['warnings']);

                Notification::make()
                    ->title("Imported: {$result['created']} new, {$result['updated']} updated")
                    ->body($problems ? implode("\n", array_slice($problems, 0, 8)).(count($problems) > 8 ? "\n…and ".(count($problems) - 8).' more' : '') : null)
                    ->{$result['errors'] ? 'warning' : 'success'}()
                    ->persistent()
                    ->send();
            });
    }

    /** @param  class-string<Sheet>  $sheetClass */
    public static function export(string $sheetClass): Action
    {
        return Action::make('export')
            ->label('Export to Excel / CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->modalSubmitActionLabel('Download')
            ->fillForm(['format' => 'xlsx', 'filtered' => true])
            ->schema([
                Radio::make('format')
                    ->options(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV'])
                    ->inline()
                    ->required(),
                Toggle::make('filtered')
                    ->label('Only the rows shown by the current search and filters'),
            ])
            ->action(function (array $data, ListRecords $livewire) use ($sheetClass): BinaryFileResponse {
                $sheet = app($sheetClass);
                $query = $data['filtered'] ? $livewire->getFilteredSortedTableQuery() : $sheet->query();

                return self::download($sheet, $data['format'], $sheet->exportRows($query));
            });
    }

    /** @param  class-string<Sheet>  $sheetClass */
    public static function template(string $sheetClass): Action
    {
        return Action::make('template')
            ->label('Empty template')
            ->icon('heroicon-o-document-arrow-down')
            ->action(fn (): BinaryFileResponse => self::download(app($sheetClass), 'xlsx', [], 'template'));
    }

    private static function download(Sheet $sheet, string $format, iterable $rows, string $suffix = ''): BinaryFileResponse
    {
        $format = $format === 'csv' ? 'csv' : 'xlsx';
        $dir = storage_path('app/private/exports');
        File::ensureDirectoryExists($dir);

        $name = $sheet->name().($suffix ? "-{$suffix}" : '-'.now()->format('Y-m-d_His')).".{$format}";
        $path = $dir.DIRECTORY_SEPARATOR.uniqid().'-'.$name;

        Spreadsheet::write($path, $format, $sheet->columns(), $rows);

        return response()->download($path, $name)->deleteFileAfterSend();
    }
}

<?php

namespace App\Services\Sheets;

use App\Support\Spreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One importable/exportable list (products, suppliers...).
 * Export columns == import columns, so a file can be exported, edited in
 * Excel and imported back.
 */
abstract class Sheet
{
    /** Short name used in file names, e.g. "products". */
    abstract public function name(): string;

    /** @return array<int, string> column headers, in order */
    abstract public function columns(): array;

    /** Extra header spellings people use, e.g. ['price' => 'sale_price']. */
    public function aliases(): array
    {
        return [];
    }

    /** @return array<int, mixed> one export row, in columns() order */
    abstract public function exportRow(mixed $record): array;

    /** Default query when exporting everything. */
    abstract public function query(): Builder;

    /**
     * Create or update one record from an import row.
     *
     * @param  array<string, ?string>  $row
     * @return 'created'|'updated'
     */
    abstract protected function importRow(array $row, array &$warnings, int $line): string;

    /** @return array{created:int, updated:int, errors:array<int,string>, warnings:array<int,string>} */
    public function import(string $path): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => [], 'warnings' => []];

        try {
            $header = $this->applyAliases(Spreadsheet::header($path));
        } catch (Throwable) {
            $result['errors'][] = 'Could not read the file. Save it as .xlsx or .csv and try again.';

            return $result;
        }

        if (! in_array('name', $header, true)) {
            $result['errors'][] = 'The first row must contain the column names, including "name". Columns: '.implode(', ', $this->columns());

            return $result;
        }

        foreach (Spreadsheet::read($path) as $line => $row) {
            $row = $this->applyAliasesToRow($row);

            if (blank($row['name'] ?? null)) {
                $result['errors'][] = "Row {$line}: name is empty";

                continue;
            }

            try {
                $outcome = DB::transaction(function () use ($row, $line, &$result) {
                    return $this->importRow($row, $result['warnings'], $line);
                });
                $result[$outcome]++;
            } catch (Throwable $e) {
                $result['errors'][] = "Row {$line}: ".$e->getMessage();
            }
        }

        return $result;
    }

    /** @return iterable<array<int, mixed>> */
    public function exportRows(Builder $query): iterable
    {
        foreach ($query->lazy(500) as $record) {
            yield $this->exportRow($record);
        }
    }

    // ----- parsing helpers for importRow() -----

    protected function number(?string $value): ?float
    {
        $value = str_replace([',', ' '], '', (string) $value);

        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("\"{$value}\" is not a number");
        }

        return (float) $value;
    }

    protected function boolean(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            '1', 'yes', 'y', 'true', 'active' => true,
            '0', 'no', 'n', 'false', 'inactive' => false,
            default => throw new \InvalidArgumentException("\"{$value}\" should be yes or no"),
        };
    }

    protected function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // A date column not formatted as a date in Excel arrives as its day number (46477 = 2027-03-31).
        if (ctype_digit($value) && (int) $value > 20000 && (int) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        throw new \InvalidArgumentException("\"{$value}\" is not a date (use YYYY-MM-DD or DD/MM/YYYY)");
    }

    private function applyAliases(array $header): array
    {
        return array_map(fn ($h) => $this->aliases()[$h] ?? $h, $header);
    }

    private function applyAliasesToRow(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $out[$this->aliases()[$key] ?? $key] ??= $value;
        }

        return $out;
    }
}

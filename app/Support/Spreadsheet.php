<?php

namespace App\Support;

use DateTimeInterface;
use Generator;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Reads and writes CSV and Excel (.xlsx) files with OpenSpout (no Office needed).
 *
 * Reading copes with what Excel produces in practice: a UTF-8 BOM, ";" as the
 * separator (some regional settings), Windows-1252 text, numbers stored as
 * floats (long barcodes) and real date cells.
 */
final class Spreadsheet
{
    public const ACCEPTED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Rows of the first sheet as [header => value], keyed by the row's line number.
     * Header names are normalised: "Sale Price" -> "sale_price".
     *
     * @return Generator<int, array<string, ?string>>
     */
    public static function read(string $path): Generator
    {
        $reader = self::isXlsx($path) ? new XlsxReader : self::csvReader($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $header = null;
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $values = array_map(self::cellToString(...), $row->toArray());

                    if ($header === null) {
                        $header = array_map(self::normaliseHeader(...), $values);

                        continue;
                    }

                    if (count(array_filter($values, fn ($v) => $v !== null)) === 0) {
                        continue; // blank row
                    }

                    $values = array_pad(array_slice($values, 0, count($header)), count($header), null);

                    yield $line => array_combine($header, $values);
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }
    }

    /** First row of the file, normalised; empty array if the file is empty. */
    public static function header(string $path): array
    {
        $reader = self::isXlsx($path) ? new XlsxReader : self::csvReader($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    return array_map(fn ($v) => self::normaliseHeader(self::cellToString($v)), $row->toArray());
                }
            }

            return [];
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  'csv'|'xlsx'  $format
     * @param  array<int, string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function write(string $path, string $format, array $header, iterable $rows): void
    {
        $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter; // CSV gets a BOM so Excel reads UTF-8
        $writer->openToFile($path);

        try {
            $writer->addRow(Row::fromValues($header, $format === 'xlsx' ? (new Style)->setFontBold() : null));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(
                    fn ($v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d') : $v,
                    array_values($row),
                )));
            }
        } finally {
            $writer->close();
        }
    }

    public static function isXlsx(string $path): bool
    {
        $handle = fopen($path, 'rb');
        $magic = $handle ? fread($handle, 4) : '';
        $handle && fclose($handle);

        return $magic === "PK\x03\x04"; // .xlsx is a zip file
    }

    private static function csvReader(string $path): CsvReader
    {
        $sample = (string) file_get_contents($path, length: 65536);
        $firstLine = strtok(preg_replace('/^\xEF\xBB\xBF/', '', $sample), "\r\n") ?: '';

        $options = new CsvReaderOptions;
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $options->FIELD_DELIMITER = (string) array_key_first($counts);

        if (! mb_check_encoding($sample, 'UTF-8')) {
            $options->ENCODING = 'Windows-1252'; // "CSV (Comma delimited)" from older Excel
        }

        return new CsvReader($options);
    }

    private static function cellToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value)) {
            // Excel stores long barcodes as numbers: 8900000000011 must not become "8.9E+12".
            $value = (floor($value) == $value && abs($value) < 1e15)
                ? sprintf('%.0f', $value)
                : rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function normaliseHeader(?string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);

        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($value))), '_');
    }
}

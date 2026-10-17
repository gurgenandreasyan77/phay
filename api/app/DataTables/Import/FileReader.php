<?php

namespace App\DataTables\Import;

/**
 * Reads an uploaded table file by its format.
 */
final class FileReader
{
    /** Rows read from one file at most. */
    public const MAX_ROWS = 20000;

    public const FORMATS = ['csv', 'tsv', 'txt', 'xlsx', 'xml'];

    public static function format(string $fileName): ?string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'tsv', 'txt' => 'csv',
            'xlsx' => 'xlsx',
            'xml' => 'xml',
            default => null,
        };
    }

    public function read(string $path, string $format, int $maxRows = self::MAX_ROWS): Sheet
    {
        $sheet = match ($format) {
            'csv' => (new CsvReader)->read($path, $maxRows),
            'xlsx' => (new XlsxReader)->read($path, $maxRows),
            'xml' => (new XmlReader)->read($path, $maxRows),
            default => throw new UnreadableFile('Այս ձևաչափը չի աջակցվում։ Օգտագործեք CSV, XLSX կամ XML։'),
        };

        if ($sheet->headers === []) {
            throw new UnreadableFile('Ֆայլը դատարկ է։');
        }

        return $sheet;
    }
}

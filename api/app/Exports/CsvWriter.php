<?php

namespace App\Exports;

/**
 * A sheet as CSV: UTF-8 with a BOM (Excel then reads Armenian letters),
 * commas, dates as 2026-10-17 and numbers with a dot, so the file can be
 * imported back. Text that a spreadsheet would run as a formula (=, +, -,
 *
 * @ first) gets a leading apostrophe.
 */
final class CsvWriter
{
    public function write(Sheet $sheet, string $path): void
    {
        $out = fopen($path, 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_column($sheet->columns, 'label'), ',', '"', '');

        $rows = $sheet->rows;
        foreach ($rows as $row) {
            fputcsv($out, $this->line($sheet, $row), ',', '"', '');
        }
        if ($sheet->total !== null) {
            fputcsv($out, $this->line($sheet, $sheet->total), ',', '"', '');
        }

        fclose($out);
    }

    /** @param  list<mixed>  $row */
    private function line(Sheet $sheet, array $row): array
    {
        $line = [];
        foreach ($sheet->columns as $i => $column) {
            $value = $row[$i] ?? null;
            $line[] = match (true) {
                $value === null => '',
                is_bool($value) => $value ? 'Այո' : 'Ոչ',
                is_int($value), is_float($value) => $this->number($value),
                default => $this->text((string) $value),
            };
        }

        return $line;
    }

    private function number(int|float $value): string
    {
        return is_int($value) ? (string) $value : rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    private function text(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value) ? "'".$value : $value;
    }
}

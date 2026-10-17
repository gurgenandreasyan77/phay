<?php

namespace App\DataTables\Import;

/**
 * A file read as a table: the header row and the data rows as lists of
 * strings, one per header (shorter rows are padded with '').
 */
final readonly class Sheet
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  bool  $truncated  the file had more rows than the import limit
     */
    public function __construct(
        public array $headers,
        public array $rows,
        public bool $truncated = false,
    ) {}

    /**
     * From raw rows: the first non-empty row is the header row; empty rows
     * are dropped; headers without a name get "Սյունակ N".
     *
     * @param  iterable<array<int, mixed>>  $rawRows
     */
    public static function fromRows(iterable $rawRows, int $maxRows): self
    {
        $headers = null;
        $rows = [];
        $truncated = false;

        foreach ($rawRows as $raw) {
            $cells = array_map(fn ($cell) => trim((string) $cell), $raw);

            if (implode('', $cells) === '') {
                continue;
            }

            if ($headers === null) {
                $headers = $cells;

                continue;
            }

            if (count($rows) >= $maxRows) {
                $truncated = true;
                break;
            }

            $rows[] = $cells;
        }

        $headers ??= [];
        $width = max(count($headers), ...array_map('count', $rows ?: [[]]));

        // Trailing columns that have neither a header nor any value.
        while ($width > 0 && ($headers[$width - 1] ?? '') === '' && ! self::columnHasValues($rows, $width - 1)) {
            $width--;
        }

        $headers = array_map(
            fn (int $i) => ($headers[$i] ?? '') !== '' ? $headers[$i] : 'Սյունակ '.($i + 1),
            range(0, max($width - 1, 0)),
        );
        if ($width === 0) {
            $headers = [];
        }

        $rows = array_map(fn (array $row) => array_map(fn (int $i) => $row[$i] ?? '', array_keys($headers)), $rows);

        return new self($headers, $rows, $truncated);
    }

    private static function columnHasValues(array $rows, int $index): bool
    {
        foreach ($rows as $row) {
            if (($row[$index] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }
}

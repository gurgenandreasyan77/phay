<?php

namespace App\DataTables\Import;

/**
 * CSV and TSV as spreadsheets and shops export them: comma, semicolon,
 * tab or pipe separated, UTF-8 (with or without BOM), or Windows-1251
 * from Russian-language software.
 */
final class CsvReader
{
    public function read(string $path, int $maxRows): Sheet
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new UnreadableFile('Ֆայլը չհաջողվեց կարդալ։');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', str_starts_with($content, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        } elseif (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
        }

        $delimiter = $this->delimiter($content);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = (function () use ($stream, $delimiter) {
            while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
                yield $row;
            }
        })();

        $sheet = Sheet::fromRows($rows, $maxRows);
        fclose($stream);

        return $sheet;
    }

    /** The separator that appears most (outside quotes) in the first lines. */
    private function delimiter(string $content): string
    {
        $sample = implode("\n", array_slice(preg_split('/\R/', $content, 6), 0, 5));
        $sample = preg_replace('/"[^"]*"/', '', $sample);

        $counts = [];
        foreach ([',', ';', "\t", '|'] as $candidate) {
            $counts[$candidate] = substr_count($sample, $candidate);
        }
        arsort($counts);

        return reset($counts) > 0 ? array_key_first($counts) : ',';
    }
}

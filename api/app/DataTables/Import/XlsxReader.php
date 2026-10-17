<?php

namespace App\DataTables\Import;

use DateTimeImmutable;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

/**
 * The first worksheet of an .xlsx file (Excel, Google Sheets, Numbers,
 * LibreOffice). Rows are streamed, so large sheets don't load at once.
 * Cells formatted as dates come out as Y-m-d.
 */
final class XlsxReader
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    // Built-in number formats that show a date.
    private const DATE_FORMAT_IDS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 45, 46, 47, 50, 51, 52, 53, 54, 55, 56, 57, 58];

    public function read(string $path, int $maxRows): Sheet
    {
        if (! class_exists(ZipArchive::class)) {
            throw new UnreadableFile('Սերվերը չի կարող կարդալ XLSX (PHP zip ընդլայնումը միացված չէ)։ Պահպանեք ֆայլը CSV ձևաչափով։');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new UnreadableFile('Ֆայլը XLSX չէ կամ վնասված է։');
        }

        try {
            $sheetXml = $zip->getFromName($this->firstSheetPath($zip));
            if ($sheetXml === false) {
                throw new UnreadableFile('Ֆայլում աղյուսակ չի գտնվել։');
            }

            $strings = $this->sharedStrings($zip);
            $dateStyles = $this->dateStyles($zip);
        } finally {
            $zip->close();
        }

        return Sheet::fromRows($this->rows($sheetXml, $strings, $dateStyles), $maxRows);
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';
        $workbook = $this->xml($zip->getFromName('xl/workbook.xml'));
        $rels = $this->xml($zip->getFromName('xl/_rels/workbook.xml.rels'));

        if (! $workbook || ! $rels) {
            return $fallback;
        }

        $sheet = $workbook->children(self::MAIN_NS)->sheets?->sheet[0] ?? null;
        $relId = $sheet ? (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';

        foreach ($rels->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $rel) {
            if ((string) self::attr($rel, 'Id') === $relId) {
                $target = (string) self::attr($rel, 'Target');

                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
        }

        return $fallback;
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $this->xml($zip->getFromName('xl/sharedStrings.xml'));
        if (! $xml) {
            return [];
        }

        $strings = [];
        foreach ($xml->children(self::MAIN_NS)->si as $si) {
            $strings[] = $this->text($si);
        }

        return $strings;
    }

    /** @return array<int, true> style indexes (the `s` attribute) that format a date */
    private function dateStyles(ZipArchive $zip): array
    {
        $xml = $this->xml($zip->getFromName('xl/styles.xml'));
        if (! $xml) {
            return [];
        }

        $main = $xml->children(self::MAIN_NS);
        $customDates = [];
        foreach ($main->numFmts?->numFmt ?? [] as $format) {
            // Drop quoted text, [colors]/[locales] and escapes, then look for day/month/year parts.
            $code = strtolower(preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', (string) self::attr($format, 'formatCode')));
            if (preg_match('/[dy]/', $code) && str_contains($code, 'm')) {
                $customDates[(int) self::attr($format, 'numFmtId')] = true;
            }
        }

        // SimpleXML iteration keys are element names, so index by position.
        $byPosition = [];
        $position = 0;
        foreach ($main->cellXfs?->xf ?? [] as $xf) {
            $id = (int) self::attr($xf, 'numFmtId');
            if (in_array($id, self::DATE_FORMAT_IDS, true) || isset($customDates[$id])) {
                $byPosition[$position] = true;
            }
            $position++;
        }

        return $byPosition;
    }

    /**
     * @param  list<string>  $strings
     * @param  array<int, true>  $dateStyles
     * @return \Generator<list<string>>
     */
    private function rows(string $sheetXml, array $strings, array $dateStyles): \Generator
    {
        $reader = XMLReader::XML($sheetXml, null, LIBXML_NONET | LIBXML_COMPACT);
        $expectedRow = 1;

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            $rowNumber = (int) ($reader->getAttribute('r') ?: $expectedRow);
            // Rows the sheet skipped are empty rows: Sheet drops them anyway.
            $expectedRow = $rowNumber + 1;

            $row = simplexml_load_string($reader->readOuterXml());
            if ($row === false) {
                continue;
            }

            $cells = [];
            $next = 0;
            foreach ($row->children(self::MAIN_NS)->c as $cell) {
                $ref = (string) self::attr($cell, 'r');
                $index = $ref !== '' ? $this->columnIndex($ref) : $next;
                $next = $index + 1;
                $cells[$index] = $this->cellValue($cell, $strings, $dateStyles);
            }

            if ($cells === []) {
                continue;
            }

            $full = array_fill(0, max(array_keys($cells)) + 1, '');
            foreach ($cells as $index => $value) {
                $full[$index] = $value;
            }

            yield $full;
        }

        $reader->close();
    }

    private function cellValue(SimpleXMLElement $cell, array $strings, array $dateStyles): string
    {
        $type = (string) self::attr($cell, 't');
        $main = $cell->children(self::MAIN_NS);
        $value = isset($main->v) ? (string) $main->v : '';

        return match ($type) {
            's' => $strings[(int) $value] ?? '',
            'inlineStr' => isset($main->is) ? $this->text($main->is) : '',
            'b' => $value === '1' ? 'TRUE' : 'FALSE',
            'e' => '',
            'str' => $value,
            default => $this->number($value, self::attr($cell, 's') !== null && isset($dateStyles[(int) self::attr($cell, 's')])),
        };
    }

    private function number(string $value, bool $isDate): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return $value;
        }

        if ($isDate) {
            $days = (int) floor((float) $value);

            return (new DateTimeImmutable('1899-12-30'))->modify("+{$days} days")->format('Y-m-d');
        }

        if (ctype_digit(ltrim($value, '-'))) {
            return $value;
        }

        // 4500.0000000001 (float noise from formulas) reads as 4500.
        return (string) round((float) $value, 9);
    }

    /** Text of a shared or inline string, rich text runs included. */
    private function text(SimpleXMLElement $node): string
    {
        $main = $node->children(self::MAIN_NS);

        if (isset($main->t)) {
            return (string) $main->t;
        }

        $text = '';
        foreach ($main->r as $run) {
            $text .= (string) $run->children(self::MAIN_NS)->t;
        }

        return $text;
    }

    /**
     * An unprefixed attribute. Read through children(MAIN_NS), plain
     * $node['name'] would look for it in that namespace and find nothing.
     */
    private static function attr(SimpleXMLElement $node, string $name): ?string
    {
        $value = $node->attributes()[$name] ?? null;

        return $value === null ? null : (string) $value;
    }

    /** "C12" → 2 */
    private function columnIndex(string $ref): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($ref));
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function xml(string|false $content): ?SimpleXMLElement
    {
        if ($content === false || $content === '') {
            return null;
        }

        return simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET) ?: null;
    }
}

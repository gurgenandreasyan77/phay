<?php

namespace App\Exports;

use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

/**
 * Sheets as an .xlsx workbook (Excel, Google Sheets, Numbers, LibreOffice
 * open it), written by hand like XlsxReader reads it: no library. Every
 * sheet has a bold, frozen header with filters, real numbers (money with
 * thousands separated), real dates (dd.mm.yyyy) and text as inline
 * strings, so nothing in a cell is ever run as a formula.
 */
final class XlsxWriter
{
    // Cell styles, by their index in styles.xml (cellXfs).
    private const S_HEADER = 1;

    private const S_DATE = 2;

    private const S_DATETIME = 3;

    private const S_INTEGER = 4;

    private const S_DECIMAL = 5;

    private const S_PERCENT = 6;

    private const S_BOLD = 7;

    /** Bold versions of the number and date styles (a total row) are the plain ones plus this. */
    private const BOLD = 6;

    /** @var list<string> the range of each sheet's filled cells, for its filter */
    private array $ranges = [];

    /** @param  list<Sheet>  $sheets */
    public function write(array $sheets, string $path): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is needed to write XLSX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the XLSX file.');
        }

        $names = $this->sheetNames($sheets);
        $this->ranges = [];
        $sheetXml = array_map(fn (Sheet $sheet) => $this->sheet($sheet), $sheets);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook($names));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($sheetXml as $i => $xml) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $xml);
        }

        $zip->close();
    }

    private function sheet(Sheet $sheet): string
    {
        $columns = $sheet->columns;
        $count = max(1, count($columns));
        $widths = array_map(fn (array $c) => max(10, min(40, mb_strlen($c['label']) + 4)), $columns);
        $xml = '';
        $r = 1;

        $cells = '';
        foreach ($columns as $i => $column) {
            $cells .= $this->text($this->ref($i, $r), $column['label'], self::S_HEADER);
        }
        $xml .= "<row r=\"{$r}\">{$cells}</row>";

        foreach ($sheet->rows as $row) {
            $r++;
            $xml .= $this->row($columns, $row, $r, false, $widths);
        }
        if ($sheet->total !== null) {
            $r++;
            $xml .= $this->row($columns, $sheet->total, $r, true, $widths);
        }

        $cols = '';
        foreach ($widths as $i => $width) {
            $n = $i + 1;
            $cols .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$width}\" customWidth=\"1\"/>";
        }
        $range = '$A$1:$'.preg_replace('/(\d+)$/', '\$$1', $this->ref($count - 1, max(1, $r)));
        $this->ranges[] = $range;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .($cols !== '' ? "<cols>{$cols}</cols>" : '')
            ."<sheetData>{$xml}</sheetData>"
            .($columns !== [] ? '<autoFilter ref="'.str_replace('$', '', $range).'"/>' : '')
            .'</worksheet>';
    }

    /**
     * @param  list<array{label: string, type: string}>  $columns
     * @param  list<mixed>  $row
     * @param  list<int>  $widths
     */
    private function row(array $columns, array $row, int $r, bool $bold, array &$widths): string
    {
        $cells = '';
        $plus = $bold ? self::BOLD : 0;

        foreach ($columns as $i => $column) {
            $value = $row[$i] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $ref = $this->ref($i, $r);
            $type = $column['type'];

            if (is_bool($value)) {
                $cells .= $this->text($ref, $value ? 'Այո' : 'Ոչ', $bold ? self::S_BOLD : 0);

                continue;
            }

            if (in_array($type, [Sheet::DATE, Sheet::DATETIME], true) && is_string($value) && ($serial = $this->serial($value)) !== null) {
                $style = (strlen($value) > 10 && ! str_ends_with($value, ' 00:00') ? self::S_DATETIME : self::S_DATE) + $plus;
                $cells .= "<c r=\"{$ref}\" s=\"{$style}\"><v>{$serial}</v></c>";
                $widths[$i] = max($widths[$i], $style - $plus === self::S_DATETIME ? 17 : 12);

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $style = match ($type) {
                    Sheet::PERCENT => self::S_PERCENT,
                    Sheet::MONEY, Sheet::NUMBER => is_float($value) && floor($value) !== $value ? self::S_DECIMAL : self::S_INTEGER,
                    default => 0,
                };
                $style = $style === 0 ? ($bold ? self::S_BOLD : 0) : $style + $plus;
                $number = is_int($value) ? (string) $value : rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
                $cells .= "<c r=\"{$ref}\"".($style ? " s=\"{$style}\"" : '')."><v>{$number}</v></c>";
                $widths[$i] = max($widths[$i], min(20, strlen($number) + 4));

                continue;
            }

            $text = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            $cells .= $this->text($ref, $text, $bold ? self::S_BOLD : 0);
            $widths[$i] = max($widths[$i], min(50, mb_strlen($text) + 2));
        }

        return "<row r=\"{$r}\">{$cells}</row>";
    }

    private function text(string $ref, string $value, int $style): string
    {
        // XML 1.0 has no place for control characters; a cell holds 32 767 characters at most.
        $value = mb_substr(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '', 0, 32767);
        $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return "<c r=\"{$ref}\" t=\"inlineStr\"".($style ? " s=\"{$style}\"" : '')."><is><t xml:space=\"preserve\">{$escaped}</t></is></c>";
    }

    /** Days since 30 December 1899, with the time as a fraction of a day. */
    private function serial(string $value): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        $days = (int) (new DateTimeImmutable('1899-12-30'))->diff(new DateTimeImmutable("{$m[1]}-{$m[2]}-{$m[3]}"))->days;
        $seconds = isset($m[4]) ? (int) $m[4] * 3600 + (int) $m[5] * 60 + (int) ($m[6] ?? 0) : 0;

        return $seconds === 0 ? (string) $days : rtrim(rtrim(sprintf('%.8F', $days + $seconds / 86400), '0'), '.');
    }

    /** A1-style reference of a 0-based column and a 1-based row. */
    private function ref(int $column, int $row): string
    {
        $letters = '';
        for ($n = $column + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26).$letters;
        }

        return $letters.$row;
    }

    /**
     * Sheet names Excel accepts: unique, at most 31 characters, none of []:*?/\.
     *
     * @param  list<Sheet>  $sheets
     * @return list<string>
     */
    private function sheetNames(array $sheets): array
    {
        $names = [];
        foreach ($sheets as $i => $sheet) {
            $base = trim(preg_replace('/[\[\]:*?\/\\\\]+/u', ' ', $sheet->name), " '") ?: 'Sheet'.($i + 1);
            $name = mb_substr($base, 0, 31);
            for ($n = 2; in_array(mb_strtolower($name), array_map('mb_strtolower', $names), true); $n++) {
                $name = mb_substr($base, 0, 31 - strlen(" ({$n})"))." ({$n})";
            }
            $names[] = $name;
        }

        return $names;
    }

    private function contentTypes(int $sheets): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheets; $i++) {
            $overrides .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    /** @param  list<string>  $names */
    private function workbook(array $names): string
    {
        $sheets = '';
        foreach ($names as $i => $name) {
            $n = $i + 1;
            $sheets .= '<sheet name="'.htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8')."\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
        }

        $filters = '';
        foreach ($names as $i => $name) {
            // Filters on the header need their defined name to show in Excel.
            $filters .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$i.'" hidden="1">\''.htmlspecialchars(str_replace("'", "''", $name), ENT_XML1, 'UTF-8').'\'!'.($this->ranges[$i] ?? '$A$1').'</definedName>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            ."<sheets>{$sheets}</sheets>"
            ."<definedNames>{$filters}</definedNames>"
            .'</workbook>';
    }

    private function workbookRels(int $sheets): string
    {
        $rels = '';
        for ($i = 1; $i <= $sheets; $i++) {
            $rels .= "<Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>";
        }
        $styles = $sheets + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            ."<Relationship Id=\"rId{$styles}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>"
            .'</Relationships>';
    }

    private function styles(): string
    {
        // 0 plain, 1 header, 2 date, 3 date and time, 4 integer, 5 decimal, 6 percent; 7-12 the same in bold.
        $xf = fn (int $numFmt, int $font, int $fill = 0, int $border = 0) => "<xf numFmtId=\"{$numFmt}\" fontId=\"{$font}\" fillId=\"{$fill}\" borderId=\"{$border}\" xfId=\"0\""
            .($numFmt ? ' applyNumberFormat="1"' : '').($font ? ' applyFont="1"' : '').($fill ? ' applyFill="1"' : '').($border ? ' applyBorder="1"' : '').'/>';
        $formats = [164, 165, 3, 4, 166];

        $xfs = $xf(0, 0).$xf(0, 1, 2, 1);
        foreach ($formats as $format) {
            $xfs .= $xf($format, 0);
        }
        $xfs .= $xf(0, 1);
        foreach ($formats as $format) {
            $xfs .= $xf($format, 1);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="3">'
            .'<numFmt numFmtId="164" formatCode="dd.mm.yyyy"/>'
            .'<numFmt numFmtId="165" formatCode="dd.mm.yyyy hh:mm"/>'
            .'<numFmt numFmtId="166" formatCode="0.0&quot;%&quot;"/>'
            .'</numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEEF2F7"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="13">'.$xfs.'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}

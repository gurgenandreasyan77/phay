<?php

namespace App\DataTables\Import;

use SimpleXMLElement;

/**
 * XML exports. Two shapes:
 * - Excel 2003 "XML Spreadsheet" (Workbook/Worksheet/Table/Row/Cell/Data):
 *   the first worksheet, first row as headers;
 * - any list of records, e.g. <orders><order><name>..</name></order>...:
 *   the element with the most same-named children holds the records, and
 *   each record's attributes and child elements become columns.
 */
final class XmlReader
{
    private const SPREADSHEET_NS = 'urn:schemas-microsoft-com:office:spreadsheet';

    public function read(string $path, int $maxRows): Sheet
    {
        $content = file_get_contents($path);

        // No DTDs: they can pull in external files or blow up in size.
        if ($content === false || preg_match('/<!DOCTYPE/i', $content)) {
            throw new UnreadableFile('XML ֆայլը չհաջողվեց կարդալ։');
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new UnreadableFile('XML ֆայլը վնասված է կամ XML չէ։');
        }

        if (in_array(self::SPREADSHEET_NS, $xml->getDocNamespaces(true), true)) {
            return Sheet::fromRows($this->spreadsheetRows($xml), $maxRows);
        }

        return $this->records($xml, $maxRows);
    }

    private function spreadsheetRows(SimpleXMLElement $xml): \Generator
    {
        $worksheet = $xml->children(self::SPREADSHEET_NS)->Worksheet[0] ?? null;
        $table = $worksheet?->children(self::SPREADSHEET_NS)->Table;

        foreach ($table?->children(self::SPREADSHEET_NS)->Row ?? [] as $row) {
            $cells = [];
            $index = 0;
            foreach ($row->children(self::SPREADSHEET_NS)->Cell as $cell) {
                $at = $cell->attributes(self::SPREADSHEET_NS)['Index'];
                if ($at !== null) {
                    $index = (int) $at - 1;
                }
                $data = $cell->children(self::SPREADSHEET_NS)->Data;
                $cells[$index] = $data !== null ? trim((string) $data) : '';
                $index++;
            }

            if ($cells === []) {
                continue;
            }

            $full = array_fill(0, max(array_keys($cells)) + 1, '');
            foreach ($cells as $i => $value) {
                $full[$i] = $value;
            }

            yield $full;
        }
    }

    private function records(SimpleXMLElement $root, int $maxRows): Sheet
    {
        $records = $this->recordList($root);

        if ($records === []) {
            throw new UnreadableFile('XML ֆայլում գրառումների ցանկ չի գտնվել։');
        }

        $headers = [];
        $values = [];
        $truncated = count($records) > $maxRows;

        foreach (array_slice($records, 0, $maxRows) as $record) {
            $fields = [];
            foreach ($record->attributes() as $name => $value) {
                $fields[(string) $name] = (string) $value;
            }
            foreach ($record->children() as $name => $child) {
                // Nested groups are flattened one level: <client><name>..</name></client> → "client name".
                if ($child->count() > 0) {
                    foreach ($child->children() as $subName => $sub) {
                        $fields[$name.' '.$subName] = trim((string) $sub);
                    }
                } else {
                    $fields[$name] = trim((string) $child);
                }
            }
            if ($fields === [] && trim((string) $record) !== '') {
                $fields[$record->getName()] = trim((string) $record);
            }

            foreach (array_keys($fields) as $name) {
                $headers[$name] ??= count($headers);
            }
            $values[] = $fields;
        }

        $names = array_keys($headers);
        $rows = array_map(fn (array $fields) => array_map(fn (string $name) => $fields[$name] ?? '', $names), $values);

        return new Sheet(array_map('strval', $names), $rows, $truncated);
    }

    /**
     * The children of the element that has the most children with one name.
     *
     * @return list<SimpleXMLElement>
     */
    private function recordList(SimpleXMLElement $root): array
    {
        $best = [];
        $queue = [$root];

        while ($queue !== []) {
            $node = array_shift($queue);
            $byName = [];
            foreach ($node->children() as $name => $child) {
                $byName[$name][] = $child;
                $queue[] = $child;
            }

            foreach ($byName as $group) {
                if (count($group) > count($best)) {
                    $best = $group;
                }
            }
        }

        return $best;
    }
}

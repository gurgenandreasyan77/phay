<?php

namespace App\Documents;

use ZipArchive;

/**
 * A Word (DOCX) document written from simple marked text, the way an
 * agent writes a contract, an act or a letter: one paragraph per line,
 * "# " "## " "### " headings, "- " list items, "| a | b |" table rows,
 * **bold** and *italic*, "[center] " / "[right] " / "[justify] " at the
 * start of a line to align it, "[pagebreak]" for a new page and "---" for
 * a line across. A blank line after a blank line is an empty paragraph
 * (room for a signature). A4, 2 cm margins, Arial: Word puts in an
 * Armenian font where Arial has none.
 */
final class DocxWriter
{
    private const FONT = 'Arial';

    public function write(string $text): string
    {
        $body = $this->body(str_replace("\r\n", "\n", $text));

        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="'.DocumentReader::W.'"><w:body>'.$body
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1418" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>');
        $zip->close();
        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }

    private function body(string $text): string
    {
        $xml = '';
        $table = [];
        $blank = 0;
        $flushTable = function () use (&$table, &$xml) {
            if ($table !== []) {
                $xml .= $this->table($table);
                $table = [];
            }
        };

        foreach (explode("\n", $text) as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '|') && str_ends_with($trimmed, '|') && mb_strlen($trimmed) > 1) {
                $blank = 0;
                // |---|:---:| is the line under a header row.
                if (preg_match('/^\|[\s:|-]+\|$/', $trimmed)) {
                    if ($table !== []) {
                        $table[count($table) - 1]['header'] = true;
                    }

                    continue;
                }
                $table[] = ['cells' => array_map('trim', explode('|', trim($trimmed, '|'))), 'header' => false];

                continue;
            }
            $flushTable();

            if ($trimmed === '') {
                if (++$blank > 1) {
                    $xml .= '<w:p/>';
                }

                continue;
            }
            $blank = 0;

            if ($trimmed === '[pagebreak]') {
                $xml .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

                continue;
            }
            if (preg_match('/^(-{3,}|_{3,}|\*{3,})$/', $trimmed)) {
                $xml .= '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="808080"/></w:pBdr></w:pPr></w:p>';

                continue;
            }

            $align = null;
            if (preg_match('/^\[(center|right|justify|left)\]\s*/i', $trimmed, $m)) {
                $align = ['center' => 'center', 'right' => 'right', 'justify' => 'both', 'left' => 'left'][strtolower($m[1])];
                $trimmed = substr($trimmed, strlen($m[0]));
            }

            $style = null;
            $indent = '';
            if (preg_match('/^(#{1,3})\s+(.*)$/u', $trimmed, $m)) {
                $style = ['Title', 'Heading1', 'Heading2'][strlen($m[1]) - 1];
                $trimmed = $m[2];
            } elseif (preg_match('/^[-*•]\s+(.*)$/u', $trimmed, $m)) {
                $trimmed = "•\t".$m[1];
                $indent = '<w:tabs><w:tab w:val="left" w:pos="360"/></w:tabs><w:ind w:left="360" w:hanging="360"/>';
            } elseif (preg_match('/^(\d+[.)](?:\d+[.)]?)*)\s+(.*)$/u', $trimmed, $m)) {
                $trimmed = $m[1]."\t".$m[2];
                $indent = '<w:tabs><w:tab w:val="left" w:pos="454"/></w:tabs><w:ind w:left="454" w:hanging="454"/>';
            }

            $properties = ($style ? '<w:pStyle w:val="'.$style.'"/>' : '').$indent.($align ? '<w:jc w:val="'.$align.'"/>' : '');
            $xml .= '<w:p>'.($properties !== '' ? "<w:pPr>{$properties}</w:pPr>" : '').$this->runs($trimmed).'</w:p>';
        }
        $flushTable();

        return $xml;
    }

    /** @param  list<array{cells: list<string>, header: bool}>  $rows */
    private function table(array $rows): string
    {
        $columns = max(array_map(fn (array $r) => count($r['cells']), $rows));
        $width = intdiv(9354, max(1, $columns));
        $border = fn (string $side) => "<w:{$side} w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"808080\"/>";
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'
            .implode('', array_map($border, ['top', 'left', 'bottom', 'right', 'insideH', 'insideV']))
            .'</w:tblBorders><w:tblCellMar><w:left w:w="85" w:type="dxa"/><w:right w:w="85" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>'
            .str_repeat("<w:gridCol w:w=\"{$width}\"/>", $columns).'</w:tblGrid>';
        foreach ($rows as $row) {
            $xml .= '<w:tr>';
            for ($i = 0; $i < $columns; $i++) {
                $cell = $row['cells'][$i] ?? '';
                $text = $row['header'] ? '**'.trim($cell, '*').'**' : $cell;
                $xml .= "<w:tc><w:tcPr><w:tcW w:w=\"{$width}\" w:type=\"dxa\"/></w:tcPr><w:p><w:pPr><w:spacing w:after=\"0\"/></w:pPr>".$this->runs($text).'</w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }

        return $xml.'</w:tbl><w:p/>';
    }

    /** **bold** and *italic* as runs; tabs as Word tabs. */
    private function runs(string $text): string
    {
        $xml = '';
        foreach (preg_split('/(\*\*.+?\*\*|(?<![\w*])\*[^*\s][^*]*?\*(?![\w*]))/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $properties = '';
            if (preg_match('/^\*\*(.+)\*\*$/su', $part, $m)) {
                $properties = '<w:b/>';
                $part = $m[1];
            } elseif (preg_match('/^\*(.+)\*$/su', $part, $m)) {
                $properties = '<w:i/>';
                $part = $m[1];
            }
            $pieces = explode("\t", $part);
            $xml .= '<w:r>'.($properties ? "<w:rPr>{$properties}</w:rPr>" : '');
            foreach ($pieces as $i => $piece) {
                if ($i > 0) {
                    $xml .= '<w:tab/>';
                }
                if ($piece !== '') {
                    $xml .= '<w:t xml:space="preserve">'.htmlspecialchars($piece, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t>';
                }
            }
            $xml .= '</w:r>';
        }

        return $xml;
    }

    private function styles(): string
    {
        $font = self::FONT;
        $fonts = "<w:rFonts w:ascii=\"{$font}\" w:hAnsi=\"{$font}\" w:cs=\"{$font}\" w:eastAsia=\"{$font}\"/>";
        $heading = fn (string $id, string $name, int $size, int $before) => "<w:style w:type=\"paragraph\" w:styleId=\"{$id}\"><w:name w:val=\"{$name}\"/><w:basedOn w:val=\"Normal\"/><w:next w:val=\"Normal\"/><w:qFormat/>"
            ."<w:pPr><w:keepNext/><w:spacing w:before=\"{$before}\" w:after=\"120\"/>".($id === 'Title' ? '<w:jc w:val="center"/>' : '')."</w:pPr><w:rPr><w:b/><w:sz w:val=\"{$size}\"/><w:szCs w:val=\"{$size}\"/></w:rPr></w:style>";

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="'.DocumentReader::W.'">'
            ."<w:docDefaults><w:rPrDefault><w:rPr>{$fonts}<w:sz w:val=\"22\"/><w:szCs w:val=\"22\"/><w:lang w:val=\"hy-AM\"/></w:rPr></w:rPrDefault>"
            .'<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            .$heading('Title', 'Title', 32, 0)
            .$heading('Heading1', 'heading 1', 26, 240)
            .$heading('Heading2', 'heading 2', 23, 200)
            .'</w:styles>';
    }
}

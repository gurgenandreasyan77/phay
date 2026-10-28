<?php

namespace App\Documents;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * The text of an office document, as the model reads it: Word (DOCX),
 * OpenDocument (ODT), RTF, PowerPoint (PPTX) and web pages (HTML). One
 * paragraph per line, exactly as DocxTemplate and OdtTemplate see the
 * paragraphs, so a line the model copies from here can be replaced in the
 * file; a table's row is its cells joined by " | ", its headers and
 * footers come after the body.
 */
final class DocumentReader
{
    /** Characters the model gets of one document (about 30 pages). */
    public const MAX_CHARS = 120_000;

    /** What can be read (and attached as a document). */
    public const EXTENSIONS = ['docx', 'odt', 'rtf', 'pptx', 'html', 'htm'];

    public const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public const TEXT = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

    public const OFFICE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';

    /** @throws UnreadableDocument */
    public function text(string $content, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'docx' => $this->docx($content),
            'odt' => $this->odt($content),
            'pptx' => $this->pptx($content),
            'rtf' => self::rtf($content),
            'html', 'htm' => self::html($content),
            'txt', 'md', 'csv' => $content,
            default => throw new UnreadableDocument('Unknown document type.'),
        };

        $text = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $text)) ?? $text);

        return mb_strlen($text) > self::MAX_CHARS
            ? mb_substr($text, 0, self::MAX_CHARS)."\n\n(The document goes on: only its beginning is shown.)"
            : $text;
    }

    /**
     * Runs $use on the office file's archive (a temporary copy, removed after).
     *
     * @template T
     *
     * @param  \Closure(ZipArchive): T  $use
     * @return T
     *
     * @throws UnreadableDocument
     */
    public static function withZip(string $content, \Closure $use): mixed
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, $content);
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                throw new UnreadableDocument('Ֆայլը վնասված է կամ այլ ձևաչափի է։');
            }
            try {
                return $use($zip);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($path);
        }
    }

    /** @throws UnreadableDocument */
    public static function dom(string|false $xml): DOMDocument
    {
        $dom = new DOMDocument;
        // LIBXML_NONET: an office file never loads anything from outside.
        if ($xml === false || $xml === '' || ! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE)) {
            throw new UnreadableDocument('Ֆայլը վնասված է կամ այլ ձևաչափի է։');
        }

        return $dom;
    }

    /**
     * A Word document's parts with text, in reading order: the body, then
     * its headers and footers.
     *
     * @return list<string>
     */
    public static function docxParts(ZipArchive $zip): array
    {
        $parts = ['word/document.xml'];
        $extra = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^word/(header|footer)\d*\.xml$#', $name)) {
                $extra[] = $name;
            }
        }
        sort($extra, SORT_NATURAL);

        return [...$parts, ...$extra];
    }

    private function docx(string $content): string
    {
        return self::withZip($content, function (ZipArchive $zip) {
            if ($zip->locateName('word/document.xml') === false) {
                throw new UnreadableDocument('Ֆայլը Word (DOCX) փաստաթուղթ չէ։');
            }
            $out = [];
            foreach (self::docxParts($zip) as $part) {
                $dom = self::dom($zip->getFromName($part));
                $lines = $this->blocks($dom, new DocxTemplate);
                if ($part !== 'word/document.xml' && $lines !== []) {
                    $out[] = '['.(str_contains($part, 'header') ? 'header' : 'footer').']';
                }
                array_push($out, ...$lines);
            }

            return implode("\n", $out);
        });
    }

    private function odt(string $content): string
    {
        return self::withZip($content, function (ZipArchive $zip) {
            if ($zip->locateName('content.xml') === false) {
                throw new UnreadableDocument('Ֆայլը OpenDocument (ODT) փաստաթուղթ չէ։');
            }
            $out = $this->blocks(self::dom($zip->getFromName('content.xml')), new OdtTemplate);
            // Headers and footers live with the page styles.
            if (($styles = $zip->getFromName('styles.xml')) !== false) {
                $lines = $this->blocks(self::dom($styles), new OdtTemplate);
                if ($lines !== []) {
                    array_push($out, '[header/footer]', ...$lines);
                }
            }

            return implode("\n", $out);
        });
    }

    /**
     * Paragraphs one per line; a table row as its cells side by side (a
     * cell's paragraphs joined by " / ").
     *
     * @return list<string>
     */
    private function blocks(DOMDocument $dom, ParagraphTemplate $format): array
    {
        $xpath = $format->xpath($dom);
        $lines = [];
        $done = [];
        foreach ($xpath->query($format->paragraphQuery()) as $paragraph) {
            /** @var DOMElement $paragraph */
            if (isset($done[spl_object_id($paragraph)])) {
                continue;
            }
            $row = $format->tableRow($paragraph);
            if ($row) {
                $cells = [];
                foreach ($format->rowCells($row) as $cell) {
                    $texts = [];
                    foreach ($format->cellParagraphs($cell) as $p) {
                        $done[spl_object_id($p)] = true;
                        $text = $format->paragraphText($p);
                        if (trim($text) !== '') {
                            $texts[] = $text;
                        }
                    }
                    $cells[] = implode(' / ', $texts);
                }
                if (trim(implode('', $cells)) !== '') {
                    $lines[] = '| '.implode(' | ', $cells).' |';
                }

                continue;
            }
            $lines[] = $format->paragraphText($paragraph);
        }

        return $lines;
    }

    private function pptx(string $content): string
    {
        return self::withZip($content, function (ZipArchive $zip) {
            $slides = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                    $slides[(int) $m[1]] = $name;
                }
            }
            if ($slides === []) {
                throw new UnreadableDocument('Ֆայլը PowerPoint (PPTX) չէ։');
            }
            ksort($slides);
            $out = [];
            foreach ($slides as $n => $name) {
                $dom = self::dom($zip->getFromName($name));
                $xpath = new DOMXPath($dom);
                $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $out[] = "[slide {$n}]";
                foreach ($xpath->query('//a:p') as $p) {
                    $text = '';
                    foreach ($xpath->query('.//a:t', $p) as $t) {
                        $text .= $t->textContent;
                    }
                    if (trim($text) !== '') {
                        $out[] = $text;
                    }
                }
            }

            return implode("\n", $out);
        });
    }

    /**
     * Plain text of an RTF file: control words dropped, paragraphs and
     * tabs kept, \'hh bytes in the file's code page, \uN characters.
     */
    public static function rtf(string $content): string
    {
        if (! str_starts_with(ltrim($content), '{\rtf')) {
            throw new UnreadableDocument('Ֆայլը RTF չէ։');
        }
        $codepage = preg_match('/\\\\ansicpg(\d+)/', $content, $m) ? 'CP'.$m[1] : 'CP1252';
        // Groups that hold no text of the document.
        $skip = ['fonttbl', 'colortbl', 'stylesheet', 'info', 'pict', 'object', 'themedata', 'colorschememapping', 'datastore', 'latentstyles', 'listtable', 'listoverridetable', 'rsidtbl', 'generator', 'xmlnstbl', 'mmathPr', 'header', 'footer'];

        $out = '';
        $bytes = '';
        $stack = [];
        $ignore = false;
        $uc = 1;
        $skipChars = 0;
        $len = strlen($content);
        $flush = function () use (&$bytes, &$out, $codepage) {
            if ($bytes !== '') {
                $out .= @mb_convert_encoding($bytes, 'UTF-8', $codepage) ?: '';
                $bytes = '';
            }
        };

        for ($i = 0; $i < $len; $i++) {
            $c = $content[$i];
            if ($c === '{') {
                $stack[] = [$ignore, $uc];
                // {\* ...} is a destination a reader may ignore.
                if (substr($content, $i + 1, 2) === '\\*') {
                    $ignore = true;
                }

                continue;
            }
            if ($c === '}') {
                [$ignore, $uc] = array_pop($stack) ?? [false, 1];

                continue;
            }
            if ($c === '\\') {
                $next = $content[$i + 1] ?? '';
                if ($next === "'") {
                    if (! $ignore && $skipChars-- <= 0) {
                        $bytes .= chr(hexdec(substr($content, $i + 2, 2)));
                    }
                    $i += 3;

                    continue;
                }
                if (in_array($next, ['\\', '{', '}'], true)) {
                    if (! $ignore && $skipChars-- <= 0) {
                        $bytes .= $next;
                    }
                    $i++;

                    continue;
                }
                if (! preg_match('/\G\\\\([a-zA-Z]+)(-?\d+)? ?/', $content, $m, 0, $i)) {
                    $i++;

                    continue;
                }
                $i += strlen($m[0]) - 1;
                $word = $m[1];
                $arg = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;
                if (in_array($word, $skip, true)) {
                    $ignore = true;
                } elseif ($ignore) {
                    continue;
                } elseif ($word === 'uc') {
                    $uc = $arg ?? 1;
                } elseif ($word === 'u' && $arg !== null) {
                    $flush();
                    $out .= mb_chr($arg < 0 ? $arg + 65536 : $arg, 'UTF-8') ?: '';
                    $skipChars = $uc;
                } elseif (in_array($word, ['par', 'line', 'row', 'sect', 'page'], true)) {
                    $flush();
                    $out .= "\n";
                } elseif ($word === 'tab' || $word === 'cell') {
                    $flush();
                    $out .= $word === 'cell' ? ' | ' : "\t";
                }

                continue;
            }
            if ($c === "\r" || $c === "\n" || $ignore) {
                continue;
            }
            if ($skipChars > 0) {
                $skipChars--;

                continue;
            }
            $bytes .= $c;
        }
        $flush();

        return $out;
    }

    public static function html(string $content): string
    {
        $content = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', '', $content) ?? $content;
        $content = preg_replace('#<(br|/p|/div|/h[1-6]|/li|/tr|/table)\b[^>]*>#i', "\n", $content) ?? $content;
        $content = preg_replace('#</t[dh]>#i', ' | ', $content) ?? $content;
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    }
}

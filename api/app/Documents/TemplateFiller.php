<?php

namespace App\Documents;

use DOMDocument;
use ZipArchive;

/**
 * A template filled in: a Word (DOCX) or OpenDocument (ODT) file, or a
 * plain text (TXT, Markdown), with phrases replaced and everything else
 * (fonts, tables, logos, page layout) left as it was. Replacements go in
 * order, each over the first place of its phrase still there, so a form
 * with the same blank "________" five times is filled blank by blank; one
 * marked `all` replaces every place.
 *
 * Several copies of one Word template also come as one file, one copy per
 * page group (combine), to print all at once.
 */
final class TemplateFiller
{
    /** Templates that can be filled in place. */
    public const EXTENSIONS = ['docx', 'odt', 'txt', 'md'];

    public const MIMES = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'txt' => 'text/plain',
        'md' => 'text/plain',
        'zip' => 'application/zip',
    ];

    /**
     * @param  list<array{find: string, replace: string, all?: bool}>  $replacements
     * @return array{0: string, 1: list<string>} the filled file and the phrases not found in it
     *
     * @throws UnreadableDocument
     */
    public function fill(string $content, string $extension, array $replacements): array
    {
        return match ($extension) {
            'docx' => $this->office($content, $replacements, new DocxTemplate, fn (ZipArchive $zip) => DocumentReader::docxParts($zip)),
            'odt' => $this->office($content, $replacements, new OdtTemplate, fn () => ['content.xml', 'styles.xml']),
            'txt', 'md' => $this->text($content, $replacements),
            default => throw new UnreadableDocument('This file can\'t be filled in place.'),
        };
    }

    /**
     * Phrases of $replacements the template doesn't have (checked on a
     * copy, before filling many).
     *
     * @param  list<array{find: string, replace: string, all?: bool}>  $replacements
     * @return list<string>
     */
    public function missing(string $content, string $extension, array $replacements): array
    {
        return $this->fill($content, $extension, $replacements)[1];
    }

    /**
     * @param  list<array{find: string, replace: string, all?: bool}>  $replacements
     * @param  \Closure(ZipArchive): list<string>  $parts
     */
    private function office(string $content, array $replacements, ParagraphTemplate $format, \Closure $parts): array
    {
        $doms = DocumentReader::withZip($content, function (ZipArchive $zip) use ($parts) {
            $doms = [];
            foreach ($parts($zip) as $part) {
                $xml = $zip->getFromName($part);
                if ($xml !== false) {
                    $doms[$part] = DocumentReader::dom($xml);
                }
            }

            return $doms;
        });
        if ($doms === []) {
            throw new UnreadableDocument('Ֆայլում տեքստ չի գտնվել։');
        }

        // Each phrase first becomes a mark, then the marks their text: a value
        // never takes the place of a later phrase (an empty one written as
        // "____" is not the next "____" blank).
        $missing = [];
        foreach ($replacements as $k => $r) {
            if ($format->replace(array_values($doms), (string) $r['find'], self::mark($k), (bool) ($r['all'] ?? false)) === 0) {
                $missing[] = (string) $r['find'];
            }
        }
        foreach ($replacements as $k => $r) {
            $format->replace(array_values($doms), self::mark($k), (string) $r['replace'], true);
        }

        return [self::rewrite($content, array_map(fn (DOMDocument $dom) => $dom->saveXML(), $doms)), $missing];
    }

    /** @param  list<array{find: string, replace: string, all?: bool}>  $replacements */
    private function text(string $content, array $replacements): array
    {
        $missing = [];
        foreach ($replacements as $k => $r) {
            $find = (string) $r['find'];
            $at = $find === '' ? false : mb_strpos($content, $find);
            if ($at === false) {
                $missing[] = $find;

                continue;
            }
            $content = ($r['all'] ?? false)
                ? str_replace($find, self::mark($k), $content)
                : mb_substr($content, 0, $at).self::mark($k).mb_substr($content, $at + mb_strlen($find));
        }
        foreach ($replacements as $k => $r) {
            $content = str_replace(self::mark($k), (string) $r['replace'], $content);
        }

        return [$content, $missing];
    }

    /** A replacement's place while the others are found (private-use characters no document has). */
    private static function mark(int $k): string
    {
        return mb_chr(0xE000).$k.mb_chr(0xE001);
    }

    /**
     * Copies of one Word template as one file: the bodies one after
     * another, each from a new page; the first copy's headers, footers
     * and page layout serve all.
     *
     * @param  list<string>  $copies
     */
    public function combine(array $copies): string
    {
        $first = $copies[0];
        $main = DocumentReader::withZip($first, fn (ZipArchive $zip) => DocumentReader::dom($zip->getFromName('word/document.xml')));
        $format = new DocxTemplate;
        $body = $format->xpath($main)->query('/w:document/w:body')->item(0);
        $section = $format->xpath($main)->query('./w:sectPr', $body)->item(0);

        foreach (array_slice($copies, 1) as $copy) {
            $dom = DocumentReader::withZip($copy, fn (ZipArchive $zip) => DocumentReader::dom($zip->getFromName('word/document.xml')));
            $break = $main->createElementNS(DocumentReader::W, 'w:p');
            $run = $main->createElementNS(DocumentReader::W, 'w:r');
            $br = $main->createElementNS(DocumentReader::W, 'w:br');
            $br->setAttributeNS(DocumentReader::W, 'w:type', 'page');
            $run->appendChild($br);
            $break->appendChild($run);
            $body->insertBefore($break, $section);
            foreach (iterator_to_array($format->xpath($dom)->query('/w:document/w:body/*')) as $node) {
                if ($node->localName === 'sectPr') {
                    continue;
                }
                $body->insertBefore($main->importNode($node, true), $section);
            }
        }

        return self::rewrite($first, ['word/document.xml' => $main->saveXML()]);
    }

    /**
     * A ZIP of files by name (names made unique: "Act 2.docx").
     *
     * @param  array<int, array{name: string, content: string}>  $files
     */
    public static function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $used = [];
        foreach ($files as $file) {
            $name = $file['name'];
            $base = pathinfo($name, PATHINFO_FILENAME);
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            for ($n = 2; isset($used[mb_strtolower($name)]); $n++) {
                $name = "{$base} {$n}.{$extension}";
            }
            $used[mb_strtolower($name)] = true;
            $zip->addFromString($name, $file['content']);
        }
        $zip->close();
        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }

    /**
     * The office file with some of its parts replaced.
     *
     * @param  array<string, string>  $parts
     */
    private static function rewrite(string $content, array $parts): string
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, $content);
        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new UnreadableDocument('Ֆայլը վնասված է կամ այլ ձևաչափի է։');
            }
            foreach ($parts as $name => $xml) {
                $zip->addFromString($name, $xml);
            }
            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}

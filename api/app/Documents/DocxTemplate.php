<?php

namespace App\Documents;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/** Paragraphs of a Word (DOCX) part: w:p, text in w:t of its runs. */
final class DocxTemplate extends ParagraphTemplate
{
    private const W = DocumentReader::W;

    public function xpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W);

        return $xpath;
    }

    public function paragraphQuery(): string
    {
        return '//w:p';
    }

    protected function isParagraph(DOMNode $node): bool
    {
        return $node instanceof DOMElement && $node->namespaceURI === self::W && $node->localName === 'p';
    }

    protected function segment(DOMNode $node): ?array
    {
        if ($node instanceof DOMText) {
            $parent = $node->parentNode;

            return $parent instanceof DOMElement && $parent->namespaceURI === self::W && $parent->localName === 't'
                ? [$node->nodeValue ?? '', true]
                : ['', false];
        }
        if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
            return null;
        }

        return match ($node->localName) {
            'tab' => $node->parentNode?->localName === 'r' ? ["\t", false] : ['', false],
            'br', 'cr' => ["\n", false],
            'noBreakHyphen' => ['-', false],
            // Deleted text, field codes, properties: not what the page shows.
            'delText', 'instrText', 'rPr', 'pPr', 'fldData' => ['', false],
            default => null,
        };
    }

    protected function textBefore(DOMElement $element): DOMText
    {
        $t = $element->ownerDocument->createElementNS(self::W, 'w:t');
        $element->parentNode->insertBefore($t, $element);
        $text = $element->ownerDocument->createTextNode('');
        $t->appendChild($text);

        return $text;
    }

    protected function breakAfter(DOMText $node, string $text): DOMText
    {
        $dom = $node->ownerDocument;
        $t = $node->parentNode;
        $br = $dom->createElementNS(self::W, 'w:br');
        $next = $dom->createElementNS(self::W, 'w:t');
        $new = $dom->createTextNode($text);
        $next->appendChild($new);
        $t->parentNode->insertBefore($br, $t->nextSibling);
        $t->parentNode->insertBefore($next, $br->nextSibling);
        $this->edited($new);

        return $new;
    }

    protected function edited(DOMText $node): void
    {
        $t = $node->parentNode;
        if ($t instanceof DOMElement) {
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        }
    }

    public function tableRow(DOMElement $paragraph): ?DOMElement
    {
        for ($node = $paragraph->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->namespaceURI === self::W && $node->localName === 'tr') {
                return $node;
            }
        }

        return null;
    }

    public function rowCells(DOMElement $row): array
    {
        return iterator_to_array($this->xpath($row->ownerDocument)->query('./w:tc', $row), false);
    }

    public function cellParagraphs(DOMElement $cell): array
    {
        return iterator_to_array($this->xpath($cell->ownerDocument)->query('.//w:p', $cell), false);
    }
}

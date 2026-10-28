<?php

namespace App\Documents;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/** Paragraphs of an OpenDocument (ODT) part: text:p and text:h, text in them and their spans. */
final class OdtTemplate extends ParagraphTemplate
{
    private const TEXT = DocumentReader::TEXT;

    private const TABLE = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';

    public function xpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('text', self::TEXT);
        $xpath->registerNamespace('table', self::TABLE);

        return $xpath;
    }

    public function paragraphQuery(): string
    {
        return '//text:p | //text:h';
    }

    protected function isParagraph(DOMNode $node): bool
    {
        return $node instanceof DOMElement && $node->namespaceURI === self::TEXT && in_array($node->localName, ['p', 'h'], true);
    }

    protected function segment(DOMNode $node): ?array
    {
        if ($node instanceof DOMText) {
            return [$node->nodeValue ?? '', true];
        }
        if (! $node instanceof DOMElement) {
            return ['', false];
        }
        if ($node->namespaceURI === 'urn:oasis:names:tc:opendocument:xmlns:office:1.0' && $node->localName === 'annotation') {
            return ['', false];
        }
        if ($node->namespaceURI !== self::TEXT) {
            return null;
        }

        return match ($node->localName) {
            'tab' => ["\t", false],
            'line-break' => ["\n", false],
            's' => [str_repeat(' ', max(1, (int) ($node->getAttributeNS(self::TEXT, 'c') ?: 1))), false],
            default => null,
        };
    }

    protected function textBefore(DOMElement $element): DOMText
    {
        $text = $element->ownerDocument->createTextNode('');
        $element->parentNode->insertBefore($text, $element);

        return $text;
    }

    protected function breakAfter(DOMText $node, string $text): DOMText
    {
        $dom = $node->ownerDocument;
        $br = $dom->createElementNS(self::TEXT, 'text:line-break');
        $new = $dom->createTextNode($text);
        $node->parentNode->insertBefore($br, $node->nextSibling);
        $node->parentNode->insertBefore($new, $br->nextSibling);

        return $new;
    }

    public function tableRow(DOMElement $paragraph): ?DOMElement
    {
        for ($node = $paragraph->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->namespaceURI === self::TABLE && $node->localName === 'table-row') {
                return $node;
            }
        }

        return null;
    }

    public function rowCells(DOMElement $row): array
    {
        return iterator_to_array($this->xpath($row->ownerDocument)->query('./table:table-cell', $row), false);
    }

    public function cellParagraphs(DOMElement $cell): array
    {
        return iterator_to_array($this->xpath($cell->ownerDocument)->query('.//text:p | .//text:h', $cell), false);
    }
}

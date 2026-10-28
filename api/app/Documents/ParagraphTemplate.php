<?php

namespace App\Documents;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Text replaced inside an office file's paragraphs without touching their
 * look. A paragraph's text is spread over runs (bold here, another font
 * there, a spell-check mark in the middle of a word), so a phrase is found
 * in the paragraph's whole text and written back into the run where it
 * starts, with the formatting of that run; the runs it spanned keep only
 * what was outside it. Tabs and line breaks count as characters ("\t",
 * "\n"), the way DocumentReader shows them to the model.
 *
 * A format (Word, OpenDocument) says what a paragraph is, where its text
 * is, and how a line break is written.
 */
abstract class ParagraphTemplate
{
    abstract public function xpath(DOMDocument $dom): DOMXPath;

    /** Every paragraph of a part, in reading order. */
    abstract public function paragraphQuery(): string;

    /** Whether the node is a paragraph of this format. */
    abstract protected function isParagraph(DOMNode $node): bool;

    /** The text a node adds to its paragraph, if it is a text node to edit or a fixed element (a tab, a break); null to look inside it. */
    abstract protected function segment(DOMNode $node): ?array;

    /** A new, empty text node right before a fixed element (a tab), to write into. */
    abstract protected function textBefore(DOMElement $element): DOMText;

    /** A line break and a new text node holding $text, right after $node; returns the new text node. */
    abstract protected function breakAfter(DOMText $node, string $text): DOMText;

    /** Called on a text node whose text changed (Word must keep its spaces). */
    protected function edited(DOMText $node): void {}

    /** The table row the paragraph sits in (its nearest one), if any. */
    abstract public function tableRow(DOMElement $paragraph): ?DOMElement;

    /** @return list<DOMElement> */
    abstract public function rowCells(DOMElement $row): array;

    /** @return list<DOMElement> */
    abstract public function cellParagraphs(DOMElement $cell): array;

    public function paragraphText(DOMElement $paragraph): string
    {
        return implode('', array_column($this->segments($paragraph), 1));
    }

    /**
     * Replaces $find in the parts' paragraphs: its first place in reading
     * order, or every place ($all). A phrase never spans two paragraphs.
     *
     * @param  list<DOMDocument>  $parts
     * @return int how many places were replaced
     */
    public function replace(array $parts, string $find, string $replace, bool $all = false): int
    {
        $needle = self::normalize($find);
        $length = mb_strlen($needle);
        $count = 0;
        foreach ($parts as $dom) {
            foreach ($this->xpath($dom)->query($this->paragraphQuery()) as $paragraph) {
                $offset = 0;
                while (true) {
                    $segments = $this->segments($paragraph);
                    $text = self::normalize(implode('', array_column($segments, 1)));
                    $at = mb_strpos($text, $needle, $offset);
                    if ($at === false) {
                        break;
                    }
                    $this->write($segments, $at, $length, $replace);
                    $count++;
                    if (! $all) {
                        return $count;
                    }
                    $offset = $at + mb_strlen(self::normalize($replace));
                }
            }
        }

        return $count;
    }

    /**
     * The paragraph's pieces in order: [node, text, editable]. Text inside
     * a paragraph nested in this one (a text box, a footnote) is its own.
     *
     * @return list<array{0: DOMNode, 1: string, 2: bool}>
     */
    protected function segments(DOMElement $paragraph): array
    {
        $out = [];
        $walk = function (DOMNode $node) use (&$walk, &$out) {
            foreach ($node->childNodes as $child) {
                if ($this->isParagraph($child)) {
                    continue;
                }
                $segment = $this->segment($child);
                if ($segment !== null) {
                    $out[] = [$child, ...$segment];

                    continue;
                }
                if ($child->hasChildNodes()) {
                    $walk($child);
                }
            }
        };
        $walk($paragraph);

        return $out;
    }

    /**
     * Writes $replace over the characters [$at, $at + $length) of the
     * paragraph whose pieces are $segments.
     *
     * @param  list<array{0: DOMNode, 1: string, 2: bool}>  $segments
     */
    private function write(array $segments, int $at, int $length, string $replace): void
    {
        $end = $at + $length;
        $position = 0;
        $touched = [];
        foreach ($segments as [$node, $text, $editable]) {
            $size = mb_strlen($text);
            $from = $position;
            $position += $size;
            if ($position <= $at || $from >= $end || ($size === 0 && ($from < $at || $from >= $end))) {
                continue;
            }
            $touched[] = [$node, $text, $editable, max(0, $at - $from), min($size, $end - $from), $size];
        }
        if ($touched === []) {
            return;
        }

        // The phrase goes into the first text it touches; a phrase of tabs only gets a text of its own.
        $anchor = null;
        foreach ($touched as $i => $piece) {
            if ($piece[2]) {
                $anchor = $i;
                break;
            }
        }
        if ($anchor === null) {
            $node = $this->textBefore($touched[0][0]);
            array_unshift($touched, [$node, '', true, 0, 0, 0]);
            $anchor = 0;
        }
        $last = count($touched) - 1;

        foreach ($touched as $i => [$node, $text, $editable, $a, $b, $size]) {
            $before = $i === $anchor && $anchor === 0 ? mb_substr($text, 0, $a) : '';
            $after = $i === $last ? mb_substr($text, $b) : '';
            if (! $editable) {
                // A tab or break inside the phrase goes with it.
                $node->parentNode?->removeChild($node);

                continue;
            }
            if ($i !== $anchor) {
                $node->nodeValue = $after;
                $this->edited($node);

                continue;
            }
            $lines = explode("\n", str_replace("\r\n", "\n", $replace));
            $first = array_shift($lines);
            $node->nodeValue = $before.$first.($lines === [] ? $after : '');
            $this->edited($node);
            $current = $node;
            foreach ($lines as $k => $line) {
                $current = $this->breakAfter($current, $line.($k === count($lines) - 1 ? $after : ''));
            }
            if ($i === $last) {
                break;
            }
        }
    }

    /** No-break spaces read as spaces (the model writes plain ones); one character each, so places stay. */
    private static function normalize(string $text): string
    {
        return str_replace(["\u{00A0}", "\u{202F}"], ' ', $text);
    }
}

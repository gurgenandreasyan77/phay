<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Keeps only simple formatting in HTML a business writes for its site (a
 * blog post): paragraphs, headings, lists, emphasis, quotes and links. Any
 * other tag is unwrapped to its text, scripts and the like are dropped with
 * their content, and the only attribute kept is a safe link target.
 */
class HtmlSanitizer
{
    private const ALLOWED = ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'a', 'br', 'blockquote'];

    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript', 'head', 'title', 'meta', 'link'];

    private const VOID = ['br'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('root');

        return $root ? trim(self::children($root)) : '';
    }

    private static function children(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::node($child);
        }

        return $out;
    }

    private static function node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::DROPPED, true)) {
            return '';
        }

        if (! in_array($tag, self::ALLOWED, true)) {
            return self::children($node);
        }

        if (in_array($tag, self::VOID, true)) {
            return "<{$tag}>";
        }

        $attributes = '';
        if ($tag === 'a' && ($href = self::safeHref($node->getAttribute('href'))) !== null) {
            $attributes = ' href="'.htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8', false).'"';
        }

        return "<{$tag}{$attributes}>".self::children($node)."</{$tag}>";
    }

    /** Web, mail and phone links, and links inside the site; nothing that runs code. */
    private static function safeHref(string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || preg_match('/[\x00-\x1F]/', $href)) {
            return null;
        }

        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return $href;
        }

        if (str_starts_with($href, '#')) {
            return $href;
        }

        return preg_match('~^(https?://|mailto:|tel:)~i', $href) ? $href : null;
    }
}

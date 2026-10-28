<?php

namespace App\Media;

/**
 * Opens what PHP can't: old office files (Word .doc, PowerPoint .ppt, Excel
 * .xls, Pages…) become DOCX, PPTX or XLSX, and a PDF gives its pages, its
 * text and pictures of its first pages. The media service does it
 * (HttpDocumentConverter: LibreOffice, poppler); a fake in tests.
 */
interface DocumentConverter
{
    /** Old formats it turns into modern ones, and what each becomes. */
    public const TARGETS = [
        'doc' => 'docx', 'dot' => 'docx', 'wps' => 'docx', 'wpd' => 'docx', 'pages' => 'docx', 'rtf' => 'docx',
        'ppt' => 'pptx', 'pps' => 'pptx', 'odp' => 'pptx', 'key' => 'pptx',
        'xls' => 'xlsx', 'ods' => 'xlsx', 'numbers' => 'xlsx',
    ];

    public function isConfigured(): bool;

    /**
     * The local file converted to TARGETS[$from], as bytes.
     *
     * @throws RenderFailed (code 422 when the file can't be opened)
     */
    public function convert(string $path, string $from): string;

    /**
     * A local PDF: {pages, text, images: [{page, jpg (base64)}]} with
     * pictures of its first $images pages.
     *
     * @return array{pages: int, text: string, images: list<array{page: int, jpg: string}>}
     *
     * @throws RenderFailed (code 422 when it is no PDF)
     */
    public function pdf(string $path, int $images = 0): array;
}

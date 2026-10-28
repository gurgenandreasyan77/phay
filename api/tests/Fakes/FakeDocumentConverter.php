<?php

namespace Tests\Fakes;

use App\Media\DocumentConverter;
use App\Media\RenderFailed;

/**
 * The media service's documents side in tests: an old file becomes
 * `converted` (or is refused when it is null), a PDF reads as `pdf`.
 */
class FakeDocumentConverter implements DocumentConverter
{
    public bool $configured = true;

    public ?string $converted = null;

    /** @var array{pages: int, text: string, images: list<array{page: int, jpg: string}>} */
    public array $pdf = ['pages' => 1, 'text' => 'Text', 'images' => []];

    /** @var list<array{0: string, 1: string|int}> what was asked: [convert, from] or [pdf, images] */
    public array $calls = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function convert(string $path, string $from): string
    {
        $this->calls[] = ['convert', $from];

        return $this->converted ?? throw new RenderFailed('The file could not be opened.', 422);
    }

    public function pdf(string $path, int $images = 0): array
    {
        $this->calls[] = ['pdf', $images];

        return [...$this->pdf, 'images' => array_slice($this->pdf['images'], 0, $images)];
    }
}

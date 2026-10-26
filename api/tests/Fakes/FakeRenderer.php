<?php

namespace Tests\Fakes;

use App\Media\Renderer;
use App\Media\RenderFailed;

/** The media service in tests: a small PNG per design, and what it was given. */
class FakeRenderer implements Renderer
{
    public bool $configured = true;

    public bool $fail = false;

    /** @var list<array{html: string, width: int, height: int, transparent: bool}> */
    public array $renders = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function png(string $html, int $width, int $height, bool $transparent = false): string
    {
        if ($this->fail) {
            throw new RenderFailed('down');
        }
        $this->renders[] = compact('html', 'width', 'height', 'transparent');

        // A tenth of the real size: enough to be a real PNG of the right shape.
        $image = imagecreatetruecolor(intdiv($width, 10), intdiv($height, 10));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}

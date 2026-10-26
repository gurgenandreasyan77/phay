<?php

namespace App\Media;

/** The sizes an agent designs in, in pixels. */
enum DesignFormat: string
{
    /** Instagram/Facebook feed post, 4:5. */
    case Post = 'post';

    /** Story, Reels/TikTok cover, 9:16. */
    case Story = 'story';

    case Square = 'square';

    /** A link preview, a Facebook cover or a site banner, ~1.9:1. */
    case Landscape = 'landscape';

    /** A printed A4 flyer (150 dpi). */
    case Flyer = 'flyer';

    /** Fonts installed in the media service (media/Dockerfile), for the model. */
    public const FONTS = [
        'Noto Sans Armenian' => 'Armenian, clean sans',
        'Noto Serif Armenian' => 'Armenian, elegant serif',
        'Montserrat' => 'Latin and Cyrillic, geometric sans, strong headlines',
        'Rubik' => 'Latin and Cyrillic, rounded friendly sans',
        'Playfair Display' => 'Latin and Cyrillic, premium serif',
        'Noto Sans' => 'Latin and Cyrillic, neutral sans',
        'Noto Serif' => 'Latin and Cyrillic, neutral serif',
        'Noto Color Emoji' => 'emoji',
    ];

    /** @return array{0: int, 1: int} width, height */
    public function size(): array
    {
        return match ($this) {
            self::Post => [1080, 1350],
            self::Story => [1080, 1920],
            self::Square => [1080, 1080],
            self::Landscape => [1200, 630],
            self::Flyer => [1240, 1754],
        };
    }

    public function describe(): string
    {
        [$width, $height] = $this->size();

        return "{$this->value} {$width}×{$height}";
    }
}

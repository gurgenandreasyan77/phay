<?php

namespace App\Media;

/** The sizes an agent cuts videos in, in pixels. */
enum VideoFormat: string
{
    /** Reels, TikTok, Stories, Shorts: 9:16. */
    case Reel = 'reel';

    /** Instagram/Facebook feed: 4:5. */
    case Feed = 'feed';

    case Square = 'square';

    /** YouTube, a website: 16:9. */
    case Landscape = 'landscape';

    /** @return array{0: int, 1: int} width, height */
    public function size(): array
    {
        return match ($this) {
            self::Reel => [1080, 1920],
            self::Feed => [1080, 1350],
            self::Square => [1080, 1080],
            self::Landscape => [1920, 1080],
        };
    }

    public function describe(): string
    {
        [$width, $height] = $this->size();

        return "{$this->value} {$width}×{$height}";
    }
}

<?php

namespace App\Media;

/**
 * Turns an agent's design (a full HTML page) into a PNG: the media service
 * (HttpRenderer) in the app, a fake in tests.
 */
interface Renderer
{
    /** Without it the agents give briefs instead of pictures. */
    public function isConfigured(): bool;

    /**
     * @param  bool  $transparent  only what the page draws (a video's text overlay)
     * @return string the PNG's bytes
     *
     * @throws RenderFailed
     */
    public function png(string $html, int $width, int $height, bool $transparent = false): string;
}

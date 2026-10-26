<?php

namespace App\Media;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** The media service (media/server.js, Chromium) on the compose network. */
class HttpRenderer implements Renderer
{
    public function __construct(
        private readonly ?string $url,
        private readonly ?string $token,
        private readonly int $timeout = 40,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->url) && filled($this->token);
    }

    public function png(string $html, int $width, int $height, bool $transparent = false): string
    {
        if (! $this->isConfigured()) {
            throw new RenderFailed('The media service is not configured (MEDIA_URL, MEDIA_TOKEN).');
        }

        try {
            $response = Http::withToken((string) $this->token)
                ->timeout($this->timeout)
                ->post(rtrim((string) $this->url, '/').'/render', ['html' => $html, 'width' => $width, 'height' => $height, 'transparent' => $transparent]);
        } catch (ConnectionException $e) {
            throw new RenderFailed('The media service is not reachable: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/png')) {
            throw new RenderFailed("The media service answered {$response->status()}: ".mb_substr($response->body(), 0, 200));
        }

        return $response->body();
    }
}

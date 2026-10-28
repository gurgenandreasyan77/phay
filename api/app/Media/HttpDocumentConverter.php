<?php

namespace App\Media;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** The media service's documents side (media/documents.js) on the compose network. */
class HttpDocumentConverter implements DocumentConverter
{
    public function __construct(
        private readonly ?string $url,
        private readonly ?string $token,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->url) && filled($this->token);
    }

    public function convert(string $path, string $from): string
    {
        $file = $this->upload($path);

        return $this->call(fn (PendingRequest $http) => $http->timeout(120)->post($this->endpoint('/convert'), ['file' => $file, 'from' => $from]))->body();
    }

    public function pdf(string $path, int $images = 0): array
    {
        $file = $this->upload($path);
        $data = $this->call(fn (PendingRequest $http) => $http->timeout(150)->post($this->endpoint('/pdf'), ['file' => $file, 'images' => $images]))->json();

        return [
            'pages' => (int) ($data['pages'] ?? 0),
            'text' => (string) ($data['text'] ?? ''),
            'images' => array_values(array_filter((array) ($data['images'] ?? []), 'is_array')),
        ];
    }

    private function upload(string $path): string
    {
        $file = fopen($path, 'rb') ?: throw new RenderFailed('The file can\'t be read.');
        try {
            $response = $this->call(fn (PendingRequest $http) => $http->timeout(120)->withBody($file, 'application/octet-stream')->put($this->endpoint('/files')));
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }

        return (string) $response->json('id');
    }

    /** @param  callable(PendingRequest): Response  $request */
    private function call(callable $request): Response
    {
        if (! $this->isConfigured()) {
            throw new RenderFailed('The media service is not configured (MEDIA_URL, MEDIA_TOKEN).');
        }

        try {
            $response = $request(Http::withToken((string) $this->token)->acceptJson());
        } catch (ConnectionException $e) {
            throw new RenderFailed('The media service is not reachable: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RenderFailed((string) ($response->json('message') ?? "The media service answered {$response->status()}."), $response->status());
        }

        return $response;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) $this->url, '/').$path;
    }
}

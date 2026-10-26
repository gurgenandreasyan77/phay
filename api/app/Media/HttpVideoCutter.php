<?php

namespace App\Media;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** The media service's video side (media/video.js) on the compose network. */
class HttpVideoCutter implements VideoCutter
{
    public function __construct(
        private readonly ?string $url,
        private readonly ?string $token,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->url) && filled($this->token);
    }

    public function upload(string $path): string
    {
        $file = fopen($path, 'rb') ?: throw new RenderFailed('The clip can\'t be read.');
        try {
            $response = $this->call(fn (PendingRequest $http) => $http->timeout(120)->withBody($file, 'application/octet-stream')->put($this->endpoint('/files')));
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }

        return (string) $response->json('id');
    }

    public function probe(string $file): array
    {
        return $this->call(fn (PendingRequest $http) => $http->timeout(60)->post($this->endpoint('/probe'), ['file' => $file]))->json();
    }

    public function start(array $plan): string
    {
        return (string) $this->call(fn (PendingRequest $http) => $http->timeout(60)->post($this->endpoint('/video'), $plan))->json('id');
    }

    public function status(string $job): array
    {
        try {
            $response = Http::withToken((string) $this->token)->timeout(10)->get($this->endpoint("/video/{$job}"));
        } catch (ConnectionException) {
            return ['status' => 'unreachable', 'progress' => 0, 'error' => null];
        }
        if ($response->status() === 404) {
            return ['status' => 'gone', 'progress' => 0, 'error' => null];
        }

        return [
            'status' => (string) $response->json('status', 'unreachable'),
            'progress' => (int) $response->json('progress', 0),
            'error' => $response->json('error'),
        ];
    }

    public function download(string $job, string $path): void
    {
        $this->call(fn (PendingRequest $http) => $http->timeout(120)->sink($path)->get($this->endpoint("/video/{$job}/file")));
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

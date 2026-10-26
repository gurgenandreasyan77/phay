<?php

namespace Tests\Fakes;

use App\Media\RenderFailed;
use App\Media\VideoCutter;

/**
 * The media service's video side in tests: every clip is `clip` (length,
 * size, sound, two tiny JPG frames), jobs end as `status` says, and what
 * it was given is kept.
 */
class FakeVideoCutter implements VideoCutter
{
    public bool $configured = true;

    /** Thrown by upload/probe/start: null, or the status code (422 refused, 503 down). */
    public ?int $fail = null;

    /** @var array{duration: float, width: int, height: int, audio: bool} */
    public array $clip = ['duration' => 12.5, 'width' => 1080, 'height' => 1920, 'audio' => true];

    /** @var array{status: string, progress: int, error: ?string} */
    public array $status = ['status' => 'rendering', 'progress' => 40, 'error' => null];

    /** @var list<string> local paths uploaded */
    public array $uploads = [];

    /** @var list<array<string, mixed>> plans started */
    public array $plans = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function upload(string $path): string
    {
        $this->failIfAsked();
        $this->uploads[] = $path;

        return 'file-'.count($this->uploads);
    }

    public function probe(string $file): array
    {
        $this->failIfAsked();
        $image = imagecreatetruecolor(48, 64);
        ob_start();
        imagejpeg($image);
        $jpg = base64_encode((string) ob_get_clean());

        return [...$this->clip, 'frames' => [['t' => 1.6, 'jpg' => $jpg], ['t' => 7.8, 'jpg' => $jpg]]];
    }

    public function start(array $plan): string
    {
        $this->failIfAsked();
        $this->plans[] = $plan;

        return 'job-'.count($this->plans);
    }

    public function status(string $job): array
    {
        return $this->status;
    }

    public function download(string $job, string $path): void
    {
        file_put_contents($path, 'fake mp4 of '.$job);
    }

    private function failIfAsked(): void
    {
        if ($this->fail) {
            throw new RenderFailed($this->fail === 422 ? 'This is not a video that can be read.' : 'down', $this->fail);
        }
    }
}

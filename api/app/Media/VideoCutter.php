<?php

namespace App\Media;

/**
 * Cuts the owner's clips into a video: the media service (HttpVideoCutter,
 * ffmpeg) in the app, a fake in tests. Cutting takes a while, so it is
 * started, then asked about until the MP4 is ready to collect.
 */
interface VideoCutter
{
    public function isConfigured(): bool;

    /**
     * Hands a clip (a local file) to the service.
     *
     * @return string its id there (kept for a few hours)
     *
     * @throws RenderFailed
     */
    public function upload(string $path): string;

    /**
     * What a clip is: {duration, width, height, audio, frames: [{t, jpg (base64)}]}.
     *
     * @return array<string, mixed>
     *
     * @throws RenderFailed with the service's reason when it is no video
     */
    public function probe(string $file): array;

    /**
     * Starts cutting: {width, height, clips: [{file, start, end, speed, muted}],
     * overlays: [{png (base64), start, end}], fade}.
     *
     * @param  array<string, mixed>  $plan
     * @return string the job's id
     *
     * @throws RenderFailed
     */
    public function start(array $plan): string;

    /**
     * @return array{status: string, progress: int, error: ?string} status: queued, rendering, done or failed ("gone" when the service forgot it)
     */
    public function status(string $job): array;

    /** Saves the finished MP4 to a local file. @throws RenderFailed */
    public function download(string $job, string $path): void;
}

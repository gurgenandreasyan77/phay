<?php

namespace App\Media;

use App\Assistant\AttachmentKind;
use App\Assistant\Attachments;
use App\Models\AssistantAttachment;
use App\Models\BrandProfile;
use App\Models\User;
use App\Models\VideoJob;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Videos an agent cuts from the clips the owner attached: its plan names
 * the parts (which clip, from which second to which, faster or slower,
 * with or without sound) and the texts shown over them; the texts are
 * drawn in the brand's colours (transparent PNGs from the renderer) and
 * the media service cuts it all together with ffmpeg.
 *
 * Cutting takes a minute or two, longer than a chat answer may wait: the
 * answer says it started, the chat shows it rendering, and the finished
 * MP4 is collected (refresh) when the chat asks or the scheduler passes,
 * and kept as a file of the answer.
 *
 * One instance per request (one question): it counts the videos started
 * and collects them, which the chat ties to the answer.
 */
class VideoStudio
{
    public const EDIT = 'edit_video';

    /** Videos one question may start. */
    public const MAX_PER_QUESTION = 2;

    public const MAX_PARTS = 10;

    public const MAX_TEXTS = 12;

    public const MAX_SECONDS = 90;

    /** A video whose job the service forgot or that never ends counts as failed after this. */
    private const GIVE_UP_MINUTES = 30;

    /** @var list<VideoJob> */
    private array $started = [];

    public function __construct(
        private readonly VideoCutter $cutter,
        private readonly Renderer $renderer,
        private readonly TenantContext $tenants,
    ) {}

    public function available(): bool
    {
        return $this->cutter->isConfigured() && $this->renderer->isConfigured();
    }

    /** A new question: its own videos. */
    public function newQuestion(): void
    {
        $this->started = [];
    }

    /** @return list<VideoJob> the videos started while answering this question */
    public function started(): array
    {
        return $this->started;
    }

    /** @return array<string, mixed> the tool definition (SDK keys: inputSchema) */
    public function definition(): array
    {
        $formats = implode(', ', array_map(fn (VideoFormat $f) => $f->describe(), VideoFormat::cases()));
        $seconds = ['type' => 'number', 'minimum' => 0];

        return [
            'name' => self::EDIT,
            'description' => 'Cuts a video from the clips the owner attached ("[file #N: …, video, …]"): the parts in order, each a piece of a clip, with texts over them. It takes a minute or two: the owner sees it in the chat when it is ready. Formats: '.$formats.'.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'A short name the owner sees, in their language.'],
                    'format' => ['type' => 'string', 'enum' => array_map(fn (VideoFormat $f) => $f->value, VideoFormat::cases())],
                    'parts' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => self::MAX_PARTS,
                        'description' => 'In order. The picture is cropped to fill the format (centre).',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'file' => ['type' => 'integer', 'description' => 'The N of [file #N] (a video).'],
                                'start' => $seconds + ['description' => 'Second of the clip the part starts at.'],
                                'end' => $seconds + ['description' => 'Second of the clip it ends at.'],
                                'speed' => ['type' => 'number', 'minimum' => 0.5, 'maximum' => 2, 'description' => '1 normal, 2 twice as fast, 0.5 slow motion.'],
                                'muted' => ['type' => 'boolean', 'description' => 'Drop this part\'s own sound.'],
                            ],
                            'required' => ['file', 'start', 'end'],
                        ],
                    ],
                    'texts' => [
                        'type' => 'array',
                        'maxItems' => self::MAX_TEXTS,
                        'description' => 'Text over the video, by second of the finished video.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'text' => ['type' => 'string', 'description' => 'Short, in the owner\'s language; a line break is \n.'],
                                'start' => $seconds,
                                'end' => $seconds,
                                'position' => ['type' => 'string', 'enum' => ['top', 'middle', 'bottom']],
                                'style' => ['type' => 'string', 'enum' => ['title', 'pill', 'caption'], 'description' => 'title: big bold words; pill: on a brand-colour label (offers, prices); caption: subtitle on a dark band.'],
                            ],
                            'required' => ['text', 'start', 'end'],
                        ],
                    ],
                    'fade' => ['type' => 'boolean', 'description' => 'Fade in at the start and out at the end (default true).'],
                ],
                'required' => ['title', 'format', 'parts'],
                'additionalProperties' => false,
            ],
        ];
    }

    /** How to cut, for the agent's prompt (English). */
    public static function guide(): string
    {
        $max = self::MAX_SECONDS;

        return <<<TEXT
        Cutting videos: when the owner attaches video clips ("[file #N: name, video, length, size, sound]", you see a few frames of each with their seconds), you can cut the finished video yourself with edit_video: pick the best moments, put them in order, set the speed, mute noisy parts and add short texts. It is rendered in a minute or two and appears in the chat; you don't see the result.
        - Keep it short for social media: a reel is usually 7-30 s, at most {$max} s. Start with the strongest moment (the hook), end with the call to action.
        - Parts: seconds of the source clip (within its length). Speed 1.5-2 for long processes (cleaning, before → after), 0.5 for a satisfying detail. A clip without sound or with noise: muted.
        - Texts: by seconds of the finished video (count the parts' lengths divided by their speed). One idea per text, 2-7 words, on screen at least 1.5 s; Armenian spelled correctly. top for the hook, bottom for offers and the call to action (the app's buttons cover the very bottom and right edge, the text stays clear of them).
        - Music: the owner adds it in Instagram or TikTok (licensed tracks there); suggest the mood. You keep the clips' own sound unless muted.
        - Say in your answer what you cut and why, in a few lines, and offer changes. For a change, call edit_video again with the corrected plan (the earlier plan is in the chat).
        TEXT;
    }

    /**
     * Starts a video by the agent's plan.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> what the agent is told
     */
    public function edit(array $input, ?User $user): array
    {
        if (! $user) {
            throw new InvalidArgumentException('Videos can be made only in a kept chat.');
        }
        if (count($this->started) >= self::MAX_PER_QUESTION) {
            throw new InvalidArgumentException('No more videos for this question ('.self::MAX_PER_QUESTION.' at most).');
        }

        $format = VideoFormat::tryFrom((string) ($input['format'] ?? '')) ?? throw new InvalidArgumentException('format must be one of '.implode(', ', array_map(fn ($f) => $f->value, VideoFormat::cases())).'.');
        $title = mb_substr(trim((string) ($input['title'] ?? '')) ?: 'Video', 0, 120);
        [$parts, $clips, $total] = $this->parts($input['parts'] ?? null, $user);
        $texts = $this->texts($input['texts'] ?? [], $total);
        [$width, $height] = $format->size();

        try {
            // Each clip once, however many parts it gives.
            $ids = [];
            foreach ($clips as $id => $clip) {
                $ids[$id] = $this->cutter->upload(Storage::disk(Attachments::DISK)->path($clip->path));
            }
            $overlays = array_map(fn (array $text) => [
                'png' => base64_encode($this->renderer->png($this->overlay($text, $width, $height), $width, $height, transparent: true)),
                'start' => $text['start'],
                'end' => $text['end'],
            ], $texts);

            $job = $this->cutter->start([
                'width' => $width,
                'height' => $height,
                'clips' => array_map(fn (array $p) => [...$p, 'file' => $ids[$p['file']]], $parts),
                'overlays' => $overlays,
                'fade' => ($input['fade'] ?? true) !== false,
            ]);
        } catch (RenderFailed $e) {
            Log::warning('Video edit failed to start', ['error' => $e->getMessage()]);

            throw new InvalidArgumentException($e->getCode() === 422
                ? 'The video service refused the plan: '.$e->getMessage()
                : 'The video service is not available right now: tell the owner, and give the editing plan instead.');
        }

        $video = VideoJob::create([
            'user_id' => $user->id,
            'media_job' => $job,
            'status' => VideoJob::RENDERING,
            'title' => $title,
            'format' => $format->value,
            'plan' => ['parts' => $parts, 'texts' => $texts, 'fade' => ($input['fade'] ?? true) !== false],
            'duration' => round($total, 2),
        ]);
        $this->started[] = $video;

        return [
            'video' => $video->id,
            'status' => 'rendering',
            'length' => round($total, 1).' s',
            'note' => "[video #{$video->id}] «{$title}» is being cut; the owner will see it in the chat in a minute or two.",
        ];
    }

    /**
     * Asks the service about a rendering video; once it is ready, keeps the
     * MP4 as a file of the answer. Safe to call often (from the chat's
     * polling and from the scheduler).
     */
    public function refresh(VideoJob $video): VideoJob
    {
        if (! $video->isRendering() || ! $video->media_job) {
            return $video;
        }

        // One caller collects it; the others see it rendering a moment longer.
        return DB::transaction(function () use ($video) {
            $video = VideoJob::query()->lockForUpdate()->find($video->id) ?? $video;
            if (! $video->isRendering()) {
                return $video;
            }

            $status = $this->cutter->status($video->media_job);
            $stale = $video->created_at?->lt(now()->subMinutes(self::GIVE_UP_MINUTES));

            if ($status['status'] === 'done') {
                return $this->collect($video);
            }
            if ($status['status'] === 'failed' || $status['status'] === 'gone' || $stale) {
                $video->update([
                    'status' => VideoJob::FAILED,
                    'error' => mb_substr((string) ($status['error'] ?? ($status['status'] === 'gone' ? 'The video service restarted before it was ready.' : 'It took too long.')), 0, 300),
                ]);
            }

            return $video;
        });
    }

    /** Progress (0-100) of a rendering video, for the chat. */
    public function progress(VideoJob $video): ?int
    {
        return $video->isRendering() && $video->media_job ? $this->cutter->status($video->media_job)['progress'] : null;
    }

    private function collect(VideoJob $video): VideoJob
    {
        $disk = Storage::disk(Attachments::DISK);
        $path = 'assistant/'.$video->tenant_id.'/'.Str::uuid()->toString().'.mp4';
        $disk->makeDirectory(dirname($path));

        try {
            $this->cutter->download($video->media_job, $disk->path($path));
        } catch (Throwable $e) {
            Log::warning('Video collect failed', ['video' => $video->id, 'error' => $e->getMessage()]);
            $disk->delete($path);

            return $video;
        }

        [$width, $height] = (VideoFormat::tryFrom($video->format) ?? VideoFormat::Reel)->size();
        $attachment = AssistantAttachment::create([
            'tenant_id' => $video->tenant_id,
            'user_id' => $video->user_id,
            'assistant_message_id' => $video->assistant_message_id,
            'kind' => AttachmentKind::Video,
            'origin' => AssistantAttachment::VIDEO,
            'mime' => 'video/mp4',
            'name' => (Str::slug(Str::ascii($video->title)) ?: 'video').'.mp4',
            'title' => $video->title,
            'format' => $video->format,
            'size' => (int) $disk->size($path),
            'path' => $path,
            'width' => $width,
            'height' => $height,
            'meta' => ['duration' => $video->duration],
        ]);
        $video->update(['status' => VideoJob::DONE, 'attachment_id' => $attachment->id]);

        return $video->setRelation('attachment', $attachment);
    }

    /**
     * The parts checked against the clips (the person's own videos), the
     * clips by id and the finished length.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<int, AssistantAttachment>, 2: float}
     */
    private function parts(mixed $parts, User $user): array
    {
        if (! is_array($parts) || $parts === [] || ! array_is_list($parts) || count($parts) > self::MAX_PARTS) {
            throw new InvalidArgumentException('parts: 1 to '.self::MAX_PARTS.' parts, in order.');
        }

        $clips = AssistantAttachment::query()->of($user)->where('kind', AttachmentKind::Video)->uploads()
            ->whereKey(array_map(fn ($p) => (int) ($p['file'] ?? 0), $parts))
            ->get()
            ->keyBy('id');

        $checked = [];
        $total = 0.0;
        foreach ($parts as $i => $part) {
            $n = $i + 1;
            $clip = $clips->get((int) ($part['file'] ?? 0)) ?? throw new InvalidArgumentException("Part {$n}: no such video in this chat (use the N of a [file #N: …, video]).");
            $length = (float) ($clip->meta['duration'] ?? 0);
            $start = max(0.0, (float) ($part['start'] ?? 0));
            $end = min($length, (float) ($part['end'] ?? $length));
            $speed = min(2.0, max(0.5, (float) ($part['speed'] ?? 1)));
            if ($end - $start < 0.2) {
                throw new InvalidArgumentException("Part {$n}: start and end must be inside the clip (0-{$length} s) and at least 0.2 s apart.");
            }
            $total += ($end - $start) / $speed;
            $checked[] = ['file' => $clip->id, 'start' => round($start, 2), 'end' => round($end, 2), 'speed' => $speed, 'muted' => (bool) ($part['muted'] ?? false)];
        }

        if ($total > self::MAX_SECONDS) {
            throw new InvalidArgumentException('The video would last '.round($total, 1).' s; at most '.self::MAX_SECONDS.' s.');
        }

        return [$checked, $clips->all(), $total];
    }

    /** @return list<array{text: string, start: float, end: float, position: string, style: string}> */
    private function texts(mixed $texts, float $total): array
    {
        if (! is_array($texts) || count($texts) > self::MAX_TEXTS) {
            throw new InvalidArgumentException('texts: at most '.self::MAX_TEXTS.'.');
        }

        $checked = [];
        foreach (array_values($texts) as $i => $text) {
            $words = trim((string) ($text['text'] ?? ''));
            if ($words === '' || mb_strlen($words) > 160) {
                throw new InvalidArgumentException('Text '.($i + 1).': 1 to 160 characters.');
            }
            $start = max(0.0, min($total, (float) ($text['start'] ?? 0)));
            $end = max($start, min($total, (float) ($text['end'] ?? $total)));
            $checked[] = [
                'text' => $words,
                'start' => round($start, 2),
                'end' => round($end, 2),
                'position' => in_array($text['position'] ?? null, ['top', 'middle', 'bottom'], true) ? $text['position'] : 'bottom',
                'style' => in_array($text['style'] ?? null, ['title', 'pill', 'caption'], true) ? $text['style'] : 'title',
            ];
        }

        return $checked;
    }

    /** A text as a see-through page of the video's size, in the brand's colours. */
    private function overlay(array $text, int $width, int $height): string
    {
        $colors = array_values(array_filter(array_map(
            fn ($c) => is_array($c) && preg_match('/^#[0-9a-f]{6}$/i', (string) ($c['hex'] ?? '')) ? $c['hex'] : null,
            BrandProfile::query()->first()?->colors ?? [],
        )));
        $accent = $colors[1] ?? $colors[0] ?? '#3F7966';
        $dark = $colors[0] ?? '#203E54';

        $scale = $width / 1080;
        $px = fn (float $n) => round($n * $scale).'px';
        // Reels and TikTok cover the top bar and the bottom fifth: texts stay inside.
        $place = match ($text['position']) {
            'top' => 'top:'.$px($height * 0.12 / $scale),
            'middle' => 'top:50%;transform:translateY(-50%)',
            default => 'bottom:'.$px($height * 0.22 / $scale),
        };
        $style = match ($text['style']) {
            'pill' => "background:{$accent};color:#fff;font-size:{$px(58)};font-weight:800;padding:{$px(18)} {$px(40)};border-radius:{$px(28)};box-shadow:0 {$px(10)} {$px(30)} rgba(0,0,0,.25)",
            'caption' => "background:rgba(0,0,0,.62);color:#fff;font-size:{$px(46)};font-weight:600;padding:{$px(14)} {$px(28)};border-radius:{$px(16)}",
            default => "color:#fff;font-size:{$px(84)};font-weight:800;line-height:1.08;text-shadow:0 {$px(4)} {$px(18)} rgba(0,0,0,.55),0 0 {$px(2)} {$dark}",
        };
        $words = nl2br(e($text['text']), false);

        return <<<HTML
        <!doctype html><html><head><meta charset="utf-8"><style>
        html,body{margin:0;background:transparent}
        .frame{position:relative;width:{$width}px;height:{$height}px;overflow:hidden;font-family:'Montserrat','Noto Sans Armenian','Noto Color Emoji',sans-serif}
        .line{position:absolute;left:{$px(70)};right:{$px(150)};{$place};text-align:center}
        .line span{display:inline-block;{$style};-webkit-box-decoration-break:clone;box-decoration-break:clone}
        </style></head><body><div class="frame"><div class="line"><span>{$words}</span></div></div></body></html>
        HTML;
    }
}

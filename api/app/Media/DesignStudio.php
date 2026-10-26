<?php

namespace App\Media;

use App\Assistant\AttachmentKind;
use App\Assistant\Attachments;
use App\Assistant\ToolOutput;
use App\Models\AssistantAttachment;
use App\Models\BrandAsset;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pictures an agent makes in the chat: it writes a design as an HTML page
 * (text, colours, shapes, the business's logo and photos), the media
 * service renders it to PNG, and the PNG is kept as a file of the answer,
 * with its HTML, so the owner can pick one of several variants and the
 * agent can rework it. The agent gets each picture back to check it.
 *
 * One instance per request (one question): it counts the renders and
 * collects the pictures made, which the chat ties to the answer.
 */
class DesignStudio
{
    public const RENDER = 'render_designs';

    public const GET = 'get_design';

    /** Designs in one render_designs call: the variants to choose from. */
    public const MAX_PER_CALL = 3;

    /** Renders in one question (variants plus fixes). */
    public const MAX_PER_QUESTION = 6;

    public const MAX_HTML_BYTES = 200 * 1024;

    /** {{logo}}, {{asset:12}}, {{file:34}}: the business's pictures, put in as data: URIs. */
    private const PLACEHOLDER = '/\{\{\s*(logo|asset:(\d+)|file:(\d+))\s*\}\}/';

    private int $renders = 0;

    /** @var list<AssistantAttachment> */
    private array $produced = [];

    public function __construct(
        private readonly Renderer $renderer,
        private readonly TenantContext $tenants,
    ) {}

    /** A new question: its own renders and pictures. */
    public function newQuestion(): void
    {
        $this->renders = 0;
        $this->produced = [];
    }

    public function available(): bool
    {
        return $this->renderer->isConfigured();
    }

    /** @return list<array<string, mixed>> tool definitions (SDK keys: inputSchema) */
    public function definitions(): array
    {
        $formats = implode(', ', array_map(fn (DesignFormat $f) => $f->describe(), DesignFormat::cases()));

        return [
            [
                'name' => self::RENDER,
                'description' => 'Renders 1 to '.self::MAX_PER_CALL.' designs (each a complete HTML page) to PNG pictures the owner sees in the chat, and shows them to you. Several designs in one call are variants for the owner to choose from. Formats: '.$formats.'.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'designs' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'maxItems' => self::MAX_PER_CALL,
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string', 'description' => 'A short name the owner sees, in their language (e.g. «Մուգ կանաչ, մեծ գին»).'],
                                    'format' => ['type' => 'string', 'enum' => array_map(fn (DesignFormat $f) => $f->value, DesignFormat::cases())],
                                    'html' => ['type' => 'string', 'description' => 'A complete HTML document with inline CSS, sized exactly to the format.'],
                                ],
                                'required' => ['title', 'format', 'html'],
                            ],
                        ],
                    ],
                    'required' => ['designs'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::GET,
                'description' => 'Gives the HTML of a design made earlier in this business ("[design #N]"), to change it or to make the final version of the variant the owner chose.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['design' => ['type' => 'integer', 'description' => 'The N of [design #N].']],
                    'required' => ['design'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** How to design, for the agent's prompt (English). */
    public static function guide(): string
    {
        $fonts = collect(DesignFormat::FONTS)->map(fn (string $use, string $font) => "\"{$font}\" ({$use})")->implode(', ');
        $formats = implode(', ', array_map(fn (DesignFormat $f) => $f->describe(), DesignFormat::cases()));
        $max = self::MAX_PER_QUESTION;

        return <<<TEXT
        Making pictures: you can make finished pictures yourself with render_designs: posters, promo posts, stories, covers, banners, price cards, flyers. You write each one as a complete HTML page with inline CSS; it is rendered to PNG and the owner sees it in the chat, and you get it back to check. You cannot make photographs: the pictures are built from text, colours, shapes, gradients, inline SVG, emoji and the business's own pictures.
        - Formats: {$formats}. Make <body> margin 0 and one root element exactly that size with overflow hidden; size text in px.
        - Nothing loads from the internet (JavaScript is off and every URL is blocked). Pictures go in by placeholder only, as the src of an <img> or in url() of CSS: {{logo}} the saved logo, {{asset:ID}} a saved example picture (ids from view_brand_assets), {{file:ID}} a picture attached in this chat or made earlier ([file #ID] or [design #ID]). Use the owner's photos of their work when they fit, e.g. as a background with an overlay.
        - Fonts installed: {$fonts}. Armenian text needs an Armenian font: put "Noto Sans Armenian" or "Noto Serif Armenian" in its font-family; Latin and Cyrillic fonts fall back to them for Armenian letters.
        - Use the brand's exact hex colours, its style and what it must avoid. Texts in the owner's language (Armenian with a Russian line below, if the brand does that), spelled correctly, short: a headline, a price or offer, a call to action, the contacts if known. Big, readable on a phone, with room at the edges (Instagram crops and overlays the bottom of a story).
        - A new request without a chosen direction: make 3 clearly different variants in one call, all in the same format (layout, mood, which colour leads) and ask which one to develop; the owner picks in the chat ("design #N"). Then take its HTML with get_design and make the final version or the changes asked, one design. A simple change to a design you made: one design.
        - Look at every picture you get back: when text is cut, overlaps, runs off the edge or is hard to read, fix the HTML and render again before you answer. At most {$max} renders per question.
        - In your answer, say in a line or two what each variant is; don't paste the HTML, the owner sees the pictures and can download them.
        TEXT;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function render(array $input, ?User $user): ToolOutput
    {
        if (! $user) {
            throw new InvalidArgumentException('Pictures can be made only in a kept chat.');
        }
        $designs = $input['designs'] ?? null;
        if (! is_array($designs) || $designs === [] || ! array_is_list($designs)) {
            throw new InvalidArgumentException('Pass designs: a list of {title, format, html}.');
        }
        if (count($designs) > self::MAX_PER_CALL) {
            throw new InvalidArgumentException('At most '.self::MAX_PER_CALL.' designs in one call.');
        }
        if ($this->renders + count($designs) > self::MAX_PER_QUESTION) {
            throw new InvalidArgumentException('No more renders for this question ('.self::MAX_PER_QUESTION.' at most): answer with the pictures you made.');
        }

        $blocks = [];
        foreach ($designs as $i => $design) {
            $number = $i + 1;
            $title = trim((string) ($design['title'] ?? ''));
            $format = DesignFormat::tryFrom((string) ($design['format'] ?? ''));
            $html = (string) ($design['html'] ?? '');
            if (! $format) {
                throw new InvalidArgumentException("Design {$number}: format must be one of ".implode(', ', array_map(fn ($f) => $f->value, DesignFormat::cases())).'.');
            }
            if (trim($html) === '' || strlen($html) > self::MAX_HTML_BYTES) {
                throw new InvalidArgumentException("Design {$number}: html is empty or bigger than 200 KB.");
            }

            [$filled, $missing] = $this->fill($html, $user);
            [$width, $height] = $format->size();
            $this->renders++;
            try {
                $png = $this->renderer->png($filled, $width, $height);
            } catch (RenderFailed $e) {
                Log::warning('Design render failed', ['error' => $e->getMessage()]);

                throw new InvalidArgumentException('The picture service is not available right now: tell the owner, and give the design as a brief instead.');
            }

            $attachment = $this->keep($png, $title === '' ? "Design {$number}" : $title, $format, $html, $user);
            $this->produced[] = $attachment;

            $blocks[] = ['type' => 'text', 'text' => "[design #{$attachment->id}] «{$attachment->title}», {$format->describe()}".($missing === [] ? '' : '. Not found, left empty: '.implode(', ', $missing))];
            $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => base64_encode($png)]];
        }

        return new ToolOutput($blocks);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function get(array $input, ?User $user): array
    {
        $design = $user
            ? AssistantAttachment::query()->of($user)->where('origin', AssistantAttachment::DESIGN)->find((int) ($input['design'] ?? 0))
            : null;
        if (! $design) {
            throw new InvalidArgumentException('No such design in this chat.');
        }

        return ['design' => $design->id, 'title' => $design->title, 'format' => $design->format, 'html' => $design->html];
    }

    /**
     * The pictures made while answering this question, to keep with the answer.
     *
     * @return list<AssistantAttachment>
     */
    public function produced(): array
    {
        return $this->produced;
    }

    /**
     * The HTML with the business's pictures in place of the placeholders,
     * and the placeholders that point at nothing.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function fill(string $html, User $user): array
    {
        $missing = [];
        $uris = [];
        $filled = preg_replace_callback(self::PLACEHOLDER, function (array $m) use ($user, &$missing, &$uris) {
            $key = $m[1];
            if (! array_key_exists($key, $uris)) {
                $file = match (true) {
                    $key === 'logo' => BrandAsset::query()->where('kind', BrandAsset::LOGO)->latest('id')->first(),
                    ($m[2] ?? '') !== '' => BrandAsset::query()->find((int) $m[2]),
                    default => AssistantAttachment::query()->of($user)->where('kind', AttachmentKind::Image)->find((int) $m[3]),
                };
                $content = $file ? Storage::disk(Attachments::DISK)->get($file->path) : null;
                $uris[$key] = $content === null ? null : 'data:'.$file->mime.';base64,'.base64_encode($content);
                if ($uris[$key] === null) {
                    $missing[] = '{{'.$key.'}}';
                }
            }

            // An empty picture rather than a broken one.
            return $uris[$key] ?? 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
        }, $html);

        return [$filled ?? $html, $missing];
    }

    private function keep(string $png, string $title, DesignFormat $format, string $html, User $user): AssistantAttachment
    {
        $path = 'assistant/'.$this->tenants->id().'/'.Str::uuid()->toString().'.png';
        Storage::disk(Attachments::DISK)->put($path, $png);
        $size = @getimagesizefromstring($png) ?: [null, null];
        $title = mb_substr($title, 0, 120);

        return AssistantAttachment::create([
            'user_id' => $user->id,
            'kind' => AttachmentKind::Image,
            'origin' => AssistantAttachment::DESIGN,
            'mime' => 'image/png',
            'name' => (Str::slug(Str::ascii($title)) ?: 'design').'.png',
            'title' => $title,
            'format' => $format->value,
            'size' => strlen($png),
            'path' => $path,
            'width' => $size[0],
            'height' => $size[1],
            'html' => $html,
        ]);
    }
}

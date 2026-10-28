<?php

namespace App\Assistant;

use App\DataTables\Import\UnreadableFile;
use App\DataTables\Import\XlsxReader;
use App\Documents\DocumentReader;
use App\Documents\UnreadableDocument;
use App\Media\RenderFailed;
use App\Media\VideoCutter;
use App\Models\AssistantAttachment;
use App\Models\BrandAsset;
use App\Models\User;
use App\Models\VideoJob;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Files in the AI chat: what may be attached (checked by content, not by
 * the name), where it is kept (the private disk, a folder per business)
 * and how the model receives it (image and document blocks of the
 * Messages API; a video as a few of its frames, App\Media\VideoStudio
 * cuts it). The limits keep a question inside one request of the
 * business's monthly AI requests without making it a heavy one.
 */
class Attachments
{
    public const DISK = 'local';

    /** Files sent with one question. */
    public const MAX_PER_MESSAGE = 4;

    /** Uploaded and not sent yet, per person: the rest is refused until they are sent or removed. */
    public const MAX_PENDING = 10;

    /** Files of the conversation the model receives with a question, the newest; older ones are named only. */
    public const MAX_IN_CONTEXT = 6;

    /** Bytes the files of one question may weigh together (the API takes 32 MB per request, base64 included). */
    public const MAX_CONTEXT_BYTES = 18 * 1024 * 1024;

    /** The Messages API's own limits for an image. */
    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public const MAX_IMAGE_SIDE = 8000;

    public const MAX_PDF_BYTES = 10 * 1024 * 1024;

    /** Pages of a PDF: every page is read as text and as a picture, so long ones cost a lot. */
    public const MAX_PDF_PAGES = 30;

    public const MAX_TEXT_BYTES = 512 * 1024;

    /** A clip, as php.ini lets it in (upload_max_filesize 80M). */
    public const MAX_VIDEO_BYTES = 80 * 1024 * 1024;

    /** Seconds of a clip: longer ones are filmed for something else than a reel. */
    public const MAX_VIDEO_SECONDS = 180;

    /** Unsent uploads are removed after this many hours (platform:assistant:prune). */
    public const PENDING_HOURS = 24;

    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    private const TEXT_EXTENSIONS = ['txt', 'csv', 'md'];

    private const VIDEO_TYPES = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm', 'video/x-m4v' => 'm4v'];

    public const UNSUPPORTED = 'Կարելի է կցել նկար (JPG, PNG, WEBP, GIF), տեսանյութ (MP4, MOV, WEBM), PDF, Word (DOCX), ODT, RTF, PowerPoint (PPTX), Excel (XLSX), HTML կամ տեքստային ֆայլ (TXT, CSV, MD)։';

    /** A Word, OpenDocument, RTF, PowerPoint file or a web page. */
    public const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;

    /** Old binary formats nothing here reads: saved again in the new one, they come in. */
    private const OLD_FORMATS = ['doc' => 'DOCX', 'ppt' => 'PPTX', 'xls' => 'XLSX', 'pages' => 'DOCX', 'odp' => 'PPTX', 'ods' => 'XLSX'];

    /** What a document is stored as (by its extension, once its content is read). */
    private const DOCUMENT_MIMES = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'rtf' => 'application/rtf',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'html' => 'text/html',
        'htm' => 'text/html',
    ];

    /** An Excel file: its first sheet. */
    public const MAX_SHEET_BYTES = 5 * 1024 * 1024;

    /** Rows of an Excel file the model reads as text (copy_table copies all). */
    private const SHEET_ROWS_SHOWN = 300;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly VideoCutter $cutter,
    ) {}

    /** Accepted types, for the file picker and the validation rule. */
    public static function extensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'xlsx', ...DocumentReader::EXTENSIONS, ...self::TEXT_EXTENSIONS, ...array_values(self::VIDEO_TYPES)];
    }

    /**
     * What the file really is, refused with the reason when it can't go to
     * the model.
     *
     * @return array{kind: AttachmentKind, mime: string, extension: string, width: ?int, height: ?int}
     *
     * @throws ValidationException
     */
    public function inspect(UploadedFile $file, bool $imagesOnly = false, string $field = 'file'): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages([$field => [$message]]);

        if (! $file->isValid()) {
            $fail('Ֆայլը չհաջողվեց վերբեռնել։ Փորձեք կրկին։');
        }

        // finfo reads the content: a renamed .exe is not a picture.
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension());
        $size = (int) $file->getSize();

        if (isset(self::IMAGE_TYPES[$mime])) {
            if ($size > self::MAX_IMAGE_BYTES) {
                $fail('Նկարը պետք է լինի մինչև 5 ՄԲ։');
            }
            $info = @getimagesize($file->getRealPath());
            if ($info === false || ($info['mime'] ?? null) !== $mime) {
                $fail('Նկարը չի բացվում։ Փորձեք այլ ֆայլ։');
            }
            if ($info[0] > self::MAX_IMAGE_SIDE || $info[1] > self::MAX_IMAGE_SIDE) {
                $fail('Նկարը շատ մեծ է (առավելագույնը 8000×8000 պիքսել)։');
            }

            return ['kind' => AttachmentKind::Image, 'mime' => $mime, 'extension' => self::IMAGE_TYPES[$mime], 'width' => $info[0], 'height' => $info[1]];
        }

        if ($imagesOnly) {
            $fail('Ընտրեք նկար (JPG, PNG, WEBP կամ GIF)։');
        }

        if (isset(self::VIDEO_TYPES[$mime])) {
            if (! $this->cutter->isConfigured()) {
                $fail('Տեսանյութեր դեռ չեն ընդունվում։ Կցեք մի քանի կադր որպես նկար։');
            }
            if ($size > self::MAX_VIDEO_BYTES) {
                $fail('Տեսանյութը պետք է լինի մինչև 80 ՄԲ։');
            }

            return ['kind' => AttachmentKind::Video, 'mime' => $mime, 'extension' => self::VIDEO_TYPES[$mime], 'width' => null, 'height' => null];
        }

        if ($mime === 'application/pdf') {
            if ($size > self::MAX_PDF_BYTES) {
                $fail('PDF-ը պետք է լինի մինչև 10 ՄԲ։');
            }
            if (self::pdfPages($file->getRealPath()) > self::MAX_PDF_PAGES) {
                $fail('PDF-ը պետք է լինի մինչև '.self::MAX_PDF_PAGES.' էջ։');
            }

            return ['kind' => AttachmentKind::Pdf, 'mime' => $mime, 'extension' => 'pdf', 'width' => null, 'height' => null];
        }

        if ($extension === 'xlsx' && in_array($mime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], true)) {
            if ($size > self::MAX_SHEET_BYTES) {
                $fail('Excel ֆայլը պետք է լինի մինչև 5 ՄԲ։');
            }
            try {
                (new XlsxReader)->read($file->getRealPath(), 1);
            } catch (UnreadableFile $e) {
                $fail($e->getMessage());
            }

            return ['kind' => AttachmentKind::Sheet, 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'extension' => 'xlsx', 'width' => null, 'height' => null];
        }

        if (in_array($extension, DocumentReader::EXTENSIONS, true)) {
            if ($size > self::MAX_DOCUMENT_BYTES) {
                $fail('Փաստաթուղթը պետք է լինի մինչև 10 ՄԲ։');
            }
            try {
                $text = (new DocumentReader)->text((string) file_get_contents($file->getRealPath()), $extension);
            } catch (UnreadableDocument $e) {
                $fail($e->getMessage());
            }
            if (trim($text) === '') {
                $fail('Փաստաթղթում տեքստ չկա (գուցե այն սկանավորված նկար է. ուղարկեք PDF կամ նկար)։');
            }

            return ['kind' => AttachmentKind::Document, 'mime' => self::DOCUMENT_MIMES[$extension], 'extension' => $extension, 'width' => null, 'height' => null];
        }

        if (isset(self::OLD_FORMATS[$extension])) {
            $fail('Այս հին ձևաչափը չի կարդացվում։ Բացեք ֆայլը և պահեք որպես '.self::OLD_FORMATS[$extension].' կամ PDF, հետո կցեք։');
        }

        if (in_array($extension, self::TEXT_EXTENSIONS, true) && (str_starts_with($mime, 'text/') || in_array($mime, ['application/csv', 'application/x-empty'], true))) {
            if ($size > self::MAX_TEXT_BYTES) {
                $fail('Տեքստային ֆայլը պետք է լինի մինչև 512 ԿԲ։');
            }
            $text = (string) file_get_contents($file->getRealPath());
            if (trim($text) === '') {
                $fail('Ֆայլը դատարկ է։');
            }
            if (! mb_check_encoding($text, 'UTF-8')) {
                $fail('Տեքստային ֆայլը պետք է լինի UTF-8 կոդավորմամբ։');
            }

            return ['kind' => AttachmentKind::Text, 'mime' => 'text/plain', 'extension' => $extension, 'width' => null, 'height' => null];
        }

        $fail(self::UNSUPPORTED);
    }

    /** Keeps an uploaded file for the person's next question. */
    public function upload(UploadedFile $file, User $user): AssistantAttachment
    {
        $pending = AssistantAttachment::query()->of($user)->uploads()->whereNull('assistant_message_id')->count();
        if ($pending >= self::MAX_PENDING) {
            throw ValidationException::withMessages(['file' => ['Չուղարկված ֆայլերը շատ են։ Ուղարկեք կամ հեռացրեք դրանք։']]);
        }

        $info = $this->inspect($file);
        $path = $this->store($file, 'assistant', $info['extension']);
        $meta = null;
        if ($info['kind'] === AttachmentKind::Video) {
            [$meta, $info['width'], $info['height']] = $this->probe($path);
        }

        return AssistantAttachment::create([
            'user_id' => $user->id,
            'kind' => $info['kind'],
            'mime' => $info['mime'],
            'name' => self::name($file),
            'size' => (int) $file->getSize(),
            'path' => $path,
            'width' => $info['width'],
            'height' => $info['height'],
            'meta' => $meta,
        ]);
    }

    /**
     * A clip's length, size and sound, and a few frames kept next to it
     * (what the model sees of it); a clip that can't be read is removed
     * and refused.
     *
     * @return array{0: array<string, mixed>, 1: int, 2: int}
     */
    private function probe(string $path): array
    {
        $disk = Storage::disk(self::DISK);
        $refuse = function (string $message) use ($disk, $path) {
            $disk->delete($path);

            throw ValidationException::withMessages(['file' => [$message]]);
        };

        try {
            $info = $this->cutter->probe($this->cutter->upload($disk->path($path)));
        } catch (RenderFailed $e) {
            $refuse($e->getCode() === 422
                ? 'Տեսանյութը չի բացվում։ Փորձեք այլ ֆայլ (MP4 կամ MOV)։'
                : 'Տեսանյութերի ծառայությունը հիմա հասանելի չէ։ Փորձեք մի փոքր ուշ։');
        }

        $duration = (float) ($info['duration'] ?? 0);
        if ($duration > self::MAX_VIDEO_SECONDS) {
            $refuse('Տեսանյութը պետք է լինի մինչև '.intdiv(self::MAX_VIDEO_SECONDS, 60).' րոպե։ Կտրեք այն կամ ուղարկեք մասերով։');
        }

        $frames = [];
        foreach (array_slice($info['frames'] ?? [], 0, 6) as $i => $frame) {
            $jpg = base64_decode((string) ($frame['jpg'] ?? ''), true);
            if ($jpg === false || $jpg === '' || @getimagesizefromstring($jpg) === false) {
                continue;
            }
            $file = preg_replace('/\.\w+$/', '', $path)."-frame-{$i}.jpg";
            $disk->put($file, $jpg);
            $frames[] = ['t' => round((float) ($frame['t'] ?? 0), 1), 'path' => $file];
        }

        return [[
            'duration' => round($duration, 2),
            'audio' => (bool) ($info['audio'] ?? false),
            'frames' => $frames,
        ], (int) ($info['width'] ?? 0) ?: null, (int) ($info['height'] ?? 0) ?: null];
    }

    /** Keeps an uploaded picture for the business's AI agents. */
    public function uploadBrandAsset(UploadedFile $file, string $kind, ?string $note, ?User $by): BrandAsset
    {
        $info = $this->inspect($file, imagesOnly: true);

        return $this->saveBrandAsset($kind, [
            'mime' => $info['mime'],
            'name' => self::name($file),
            'size' => (int) $file->getSize(),
            'path' => $this->store($file, 'brand', $info['extension']),
            'width' => $info['width'],
            'height' => $info['height'],
        ], $note, $by);
    }

    /**
     * A picture attached in the chat becomes the business's logo or one of
     * its examples (a copy: the chat's file goes with its conversation).
     */
    public function toBrandAsset(AssistantAttachment $attachment, string $kind, ?string $note, ?User $by): BrandAsset
    {
        if ($attachment->kind !== AttachmentKind::Image) {
            throw ValidationException::withMessages(['attachment_id' => ['Բրենդում կարելի է պահել միայն նկար։']]);
        }

        $disk = Storage::disk(self::DISK);
        $path = $this->folder('brand').'/'.Str::uuid()->toString().'.'.pathinfo($attachment->path, PATHINFO_EXTENSION);
        $disk->copy($attachment->path, $path);

        return $this->saveBrandAsset($kind, [
            'mime' => $attachment->mime,
            'name' => $attachment->name,
            'size' => $attachment->size,
            'path' => $path,
            'width' => $attachment->width,
            'height' => $attachment->height,
        ], $note, $by);
    }

    /** One logo (a new one replaces it); examples up to MAX_EXAMPLES. */
    private function saveBrandAsset(string $kind, array $file, ?string $note, ?User $by): BrandAsset
    {
        if ($kind === BrandAsset::EXAMPLE && BrandAsset::query()->where('kind', BrandAsset::EXAMPLE)->count() >= BrandAsset::MAX_EXAMPLES) {
            $this->delete([$file['path']]);

            throw ValidationException::withMessages(['kind' => ['Կարելի է պահել մինչև '.BrandAsset::MAX_EXAMPLES.' օրինակ։ Հեռացրեք հներից մեկը։']]);
        }

        if ($kind === BrandAsset::LOGO) {
            $old = BrandAsset::query()->where('kind', BrandAsset::LOGO)->get();
            $this->delete($old->pluck('path')->all());
            BrandAsset::query()->whereKey($old->modelKeys())->delete();
        }

        $note = trim((string) $note);

        return BrandAsset::create([...$file, 'kind' => $kind, 'note' => $note === '' ? null : mb_substr($note, 0, 300), 'created_by' => $by?->id]);
    }

    /**
     * The file as the Messages API takes it: an image block, or a document
     * block (a PDF, or plain text) titled with its name.
     *
     * @return array<string, mixed>
     */
    public function block(AssistantAttachment|BrandAsset $file): array
    {
        if ($file instanceof AssistantAttachment && $file->kind === AttachmentKind::Video) {
            throw new \LogicException('A video goes to the model as frames: use blocks().');
        }

        $content = Storage::disk(self::DISK)->get($file->path) ?? '';

        if ($file instanceof BrandAsset || $file->kind === AttachmentKind::Image) {
            return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $file->mime, 'data' => base64_encode($content)]];
        }

        if ($file->kind === AttachmentKind::Pdf) {
            return ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($content)], 'title' => $file->name];
        }

        if ($file->kind === AttachmentKind::Sheet) {
            return ['type' => 'document', 'source' => ['type' => 'text', 'media_type' => 'text/plain', 'data' => self::sheetText($content)], 'title' => $file->name];
        }

        if ($file->kind === AttachmentKind::Document) {
            return ['type' => 'document', 'source' => ['type' => 'text', 'media_type' => 'text/plain', 'data' => self::documentText($content, $file->name)], 'title' => $file->name];
        }

        return ['type' => 'document', 'source' => ['type' => 'text', 'media_type' => 'text/plain', 'data' => $content], 'title' => $file->name];
    }

    /**
     * What the model receives of a file: its block, or for a video its
     * frames, each after the second it was taken at.
     *
     * @return list<array<string, mixed>>
     */
    public function blocks(AssistantAttachment $file): array
    {
        if ($file->kind !== AttachmentKind::Video) {
            return [$this->block($file)];
        }

        $disk = Storage::disk(self::DISK);
        $blocks = [];
        foreach ($file->meta['frames'] ?? [] as $frame) {
            if ($disk->exists($frame['path'])) {
                $blocks[] = ['type' => 'text', 'text' => "[file #{$file->id}] at {$frame['t']} s:"];
                $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode((string) $disk->get($frame['path']))]];
            }
        }

        return $blocks;
    }

    /** Bytes a file adds to the request (a video: its frames). */
    private function weight(AssistantAttachment $file): int
    {
        if ($file->kind !== AttachmentKind::Video) {
            return $file->size;
        }
        $disk = Storage::disk(self::DISK);

        return array_sum(array_map(fn (array $f) => $disk->exists($f['path']) ? $disk->size($f['path']) : 0, $file->meta['frames'] ?? []));
    }

    /** An Excel file's first sheet as CSV text, its first rows. */
    private static function sheetText(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet');
        file_put_contents($path, $content);
        try {
            $sheet = (new XlsxReader)->read($path, self::SHEET_ROWS_SHOWN);
        } catch (UnreadableFile) {
            return '(The file could not be read.)';
        } finally {
            @unlink($path);
        }

        $out = fopen('php://temp', 'w+');
        foreach ([$sheet->headers, ...$sheet->rows] as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv.($sheet->truncated ? "\n(Only the first ".self::SHEET_ROWS_SHOWN.' rows are shown; copy_table reads them all.)' : '');
    }

    /** A document's text as the model reads it (a ZIP of documents: only its name). */
    private static function documentText(string $content, string $name): string
    {
        try {
            $text = (new DocumentReader)->text($content, strtolower(pathinfo($name, PATHINFO_EXTENSION)));
        } catch (UnreadableDocument) {
            return '(The file could not be read.)';
        }

        return $text === '' ? '(The document has no text.)' : $text;
    }

    /** How a file is named to the model: "[file #N: name, kind…]". */
    private static function label(AssistantAttachment $file, bool $shown): string
    {
        $details = '';
        if ($file->kind === AttachmentKind::Video) {
            $meta = $file->meta ?? [];
            $details = ', '.($meta['duration'] ?? '?').' s'
                .($file->width && $file->height ? ", {$file->width}×{$file->height}" : '')
                .(($meta['audio'] ?? false) ? ', with sound' : ', no sound');
        }

        return "[file #{$file->id}: {$file->name}, {$file->kind->value}{$details}".($shown ? '' : ', sent earlier, no longer shown').']';
    }

    /**
     * The conversation for the model: a question with files becomes
     * content blocks (the files, then the question naming them by number,
     * so an agent can save one to the brand). Only the newest
     * MAX_IN_CONTEXT files (within MAX_CONTEXT_BYTES) are sent again;
     * older ones are named, which keeps a long chat cheap.
     *
     * An answer names the pictures and videos its agent made, with a
     * video's plan, so the agent can change it.
     *
     * @param  list<array{role: string, content: string, files?: list<AssistantAttachment>, videos?: list<VideoJob>}>  $messages
     * @return list<array{role: string, content: string|list<array<string, mixed>>}>
     */
    public function forModel(array $messages): array
    {
        $send = [];
        $count = 0;
        $bytes = 0;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            // An answer can't carry pictures to the model: its designs are named only.
            if ($messages[$i]['role'] !== 'user') {
                continue;
            }
            foreach (array_reverse($messages[$i]['files'] ?? []) as $file) {
                $weight = $this->weight($file);
                if ($count < self::MAX_IN_CONTEXT && $bytes + $weight <= self::MAX_CONTEXT_BYTES && Storage::disk(self::DISK)->exists($file->path)) {
                    $send[$file->id] = true;
                    $count++;
                    $bytes += $weight;
                }
            }
        }

        return array_map(function (array $message) use ($send) {
            $files = $message['files'] ?? [];
            $videos = $message['videos'] ?? [];
            if ($files === [] && $videos === []) {
                return ['role' => $message['role'], 'content' => $message['content']];
            }

            // The pictures an agent made in this answer, by number (get_design opens one), and its videos with their plans.
            if ($message['role'] !== 'user') {
                $designs = array_filter($files, fn (AssistantAttachment $f) => $f->isDesign());
                $documents = array_filter($files, fn (AssistantAttachment $f) => $f->isDocument());
                $made = [];
                if ($designs !== []) {
                    $made[] = '(Pictures made in this answer: '.implode(', ', array_map(fn (AssistantAttachment $f) => "[design #{$f->id}] «{$f->title}» ({$f->format})", $designs)).')';
                }
                if ($documents !== []) {
                    $made[] = '(Documents made in this answer: '.implode(', ', array_map(fn (AssistantAttachment $f) => "[file #{$f->id}] {$f->name}", $documents)).')';
                }
                foreach ($videos as $video) {
                    $made[] = "(Video cut in this answer: [video #{$video->id}] «{$video->title}» ({$video->format}, {$video->duration} s, {$video->status}"
                        .($video->error ? ": {$video->error}" : '').'), plan: '.json_encode($video->plan, JSON_UNESCAPED_UNICODE).')';
                }

                return ['role' => $message['role'], 'content' => trim(trim($message['content'])."\n\n".implode("\n", $made))];
            }

            $blocks = [];
            $names = [];
            foreach ($files as $file) {
                $shown = isset($send[$file->id]);
                if ($shown) {
                    array_push($blocks, ...$this->blocks($file));
                }
                $names[] = self::label($file, $shown);
            }

            $question = trim($message['content']);
            $blocks[] = ['type' => 'text', 'text' => 'Attached: '.implode(' ', $names)."\n\n".($question === '' ? '(No text: the files are the message.)' : $question)];

            return ['role' => $message['role'], 'content' => $blocks];
        }, $messages);
    }

    /** Removes files from the disk (rows are the caller's). */
    public function delete(array $paths): void
    {
        $paths = array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== '' && ! str_contains($path, '..')));
        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }

    /** Uploads nobody sent within PENDING_HOURS, in every business. */
    public function prune(): int
    {
        // Designs of an answer that failed, too: nobody sees them.
        $old = AssistantAttachment::withoutTenancy()
            ->whereNull('assistant_message_id')
            ->where('created_at', '<', now()->subHours(self::PENDING_HOURS))
            ->get(['id', 'path', 'meta']);

        $this->delete($old->flatMap(fn (AssistantAttachment $a) => $a->paths())->all());
        AssistantAttachment::withoutTenancy()->whereKey($old->modelKeys())->delete();
        // Videos started for a question that failed.
        VideoJob::withoutTenancy()->whereNull('assistant_message_id')->where('created_at', '<', now()->subHours(self::PENDING_HOURS))->delete();

        return $old->count();
    }

    private function store(UploadedFile $file, string $area, string $extension): string
    {
        return $file->storeAs($this->folder($area), Str::uuid()->toString().'.'.$extension, self::DISK);
    }

    /** assistant/<tenant id> or brand/<tenant id>: one folder per business. */
    private function folder(string $area): string
    {
        return $area.'/'.$this->tenants->id();
    }

    private static function name(UploadedFile $file): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $file->getClientOriginalName()) ?? '');

        return mb_substr($name === '' ? 'file' : $name, 0, 255);
    }

    /**
     * Pages of a PDF, counted in its page tree (a rough count: a PDF whose
     * objects are compressed gives 0 and passes; the API refuses past 100).
     */
    private static function pdfPages(string $path): int
    {
        $content = (string) file_get_contents($path);
        $counts = [];
        if (preg_match_all('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $content, $m)) {
            $counts = array_map('intval', $m[1]);
        }

        return max([preg_match_all('/\/Type\s*\/Page\b(?!s)/', $content), ...$counts]);
    }
}

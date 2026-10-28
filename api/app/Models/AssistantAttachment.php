<?php

namespace App\Models;

use App\Assistant\AttachmentKind;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file a person attached to a question in the AI chat (App\Assistant\Attachments).
 * Uploaded first (assistant_message_id null), tied to the question once it
 * is answered; only its uploader ever reads it.
 *
 * Or a picture an AI agent made while answering that person (origin
 * design, App\Media\DesignStudio): tied to the answer, with the HTML it
 * was rendered from.
 */
class AssistantAttachment extends Model
{
    use BelongsToTenant;

    public const UPLOAD = 'upload';

    public const DESIGN = 'design';

    /** An MP4 an agent cut from the owner's clips (App\Media\VideoStudio). */
    public const VIDEO = 'video';

    /** A document an agent made (App\Documents\DocumentStudio): a Word file, a text, a ZIP of many. */
    public const DOCUMENT = 'document';

    protected $fillable = ['user_id', 'assistant_message_id', 'kind', 'origin', 'mime', 'name', 'title', 'format', 'size', 'path', 'width', 'height', 'html', 'meta'];

    protected $attributes = ['origin' => self::UPLOAD];

    protected $hidden = ['html', 'meta'];

    protected $casts = [
        'kind' => AttachmentKind::class,
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'meta' => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class, 'assistant_message_id');
    }

    /** The files of this person (in the current business). */
    public function scopeOf(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /** Files people attached (not pictures the agents made). */
    public function scopeUploads(Builder $query): Builder
    {
        return $query->where('origin', self::UPLOAD);
    }

    public function isDesign(): bool
    {
        return $this->origin === self::DESIGN;
    }

    public function isDocument(): bool
    {
        return $this->origin === self::DOCUMENT;
    }

    /** The file and the frames kept with it (a video's), for removal. */
    public function paths(): array
    {
        return [$this->path, ...array_column($this->meta['frames'] ?? [], 'path')];
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'name' => $this->name,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'origin' => $this->origin,
            'title' => $this->title,
            'format' => $this->format,
            // A video's length in seconds.
            'duration' => $this->meta['duration'] ?? null,
            // A ZIP's (or a print file's) number of documents.
            'documents' => $this->meta['documents'] ?? null,
        ];
    }
}

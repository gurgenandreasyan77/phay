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
 */
class AssistantAttachment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'assistant_message_id', 'kind', 'mime', 'name', 'size', 'path', 'width', 'height'];

    protected $casts = [
        'kind' => AttachmentKind::class,
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
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
        ];
    }
}

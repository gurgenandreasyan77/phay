<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A video an AI agent is cutting (or cut) from the owner's clips
 * (App\Media\VideoStudio): rendering in the media service, then done with
 * its MP4 kept as a file of the answer, or failed with the reason. Only
 * the person who asked sees it.
 */
class VideoJob extends Model
{
    use BelongsToTenant;

    public const RENDERING = 'rendering';

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected $fillable = ['user_id', 'assistant_message_id', 'attachment_id', 'media_job', 'status', 'title', 'format', 'plan', 'duration', 'error'];

    protected $casts = [
        'plan' => 'array',
        'duration' => 'float',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(AssistantAttachment::class, 'attachment_id');
    }

    public function scopeOf(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function isRendering(): bool
    {
        return $this->status === self::RENDERING;
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'title' => $this->title,
            'format' => $this->format,
            'duration' => $this->duration,
            'error' => $this->status === self::FAILED ? $this->error : null,
            'attachment' => $this->attachment?->present(),
        ];
    }
}

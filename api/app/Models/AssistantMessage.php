<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A question (role user, maybe with files) or an answer (role assistant) of an AssistantConversation. */
class AssistantMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['role', 'content', 'refused', 'actions'];

    protected $casts = [
        'refused' => 'boolean',
        'actions' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssistantAttachment::class)->orderBy('id');
    }
}

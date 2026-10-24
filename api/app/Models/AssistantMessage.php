<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A question (role user) or an answer (role assistant) of an AssistantConversation. */
class AssistantMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['role', 'content', 'refused'];

    protected $casts = [
        'refused' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }
}

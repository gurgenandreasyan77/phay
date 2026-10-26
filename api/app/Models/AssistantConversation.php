<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person's chat with the AI assistant inside a business: its questions
 * and answers (AssistantMessage), named by its first question. agent_key:
 * the AI agent it is with (App\Agents\AgentCatalog), null for the assistant.
 */
class AssistantConversation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'agent_key', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class)->orderBy('id');
    }

    /** The chats of this person (in the current business). */
    public function scopeOf(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /** "How much did we sell in March?" as a title, cut on a word. */
    public static function titleFrom(string $question): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $question));

        if (mb_strlen($text) <= 80) {
            return $text;
        }

        $cut = mb_substr($text, 0, 80);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > 40 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:').'…';
    }
}

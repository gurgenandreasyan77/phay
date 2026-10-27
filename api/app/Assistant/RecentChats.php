<?php

namespace App\Assistant;

use App\Agents\AgentCatalog;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A person's latest chats with the assistant and the agents in a business,
 * for coming back to them: the assistant page reopens the last one, and the
 * header (a floating button on a phone) shows whom they talked to.
 *
 * A chat is the person's latest by its last answer or by when they last
 * opened it, whichever is later. It is unread while an answer came after
 * they last saw it: a long answer arrives while they are on another page.
 *
 * Questions being answered right now are kept in the cache for the few
 * minutes an answer may take (a new chat has no row yet), so another page,
 * another tab or the phone sees the agent "thinking".
 */
class RecentChats
{
    /** Chats shown at most. */
    public const LIMIT = 5;

    /** An answer never takes longer (the request stops after ~2 minutes). */
    private const ASKING_TTL = 180;

    private const EXCERPT = 160;

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * The question being answered now, until end() with the token returned.
     */
    public function begin(User $user, ?string $agent, ?AssistantConversation $conversation, string $question): string
    {
        $token = Str::random(16);
        $asking = collect($this->askingRaw($user))
            // Two tabs may ask at once; a chat asks one question at a time.
            ->reject(fn (array $a) => $conversation && $a['conversationId'] === $conversation->id)
            ->push([
                'token' => $token,
                'agent' => $agent,
                'conversationId' => $conversation?->id,
                'question' => $this->excerpt($question !== '' ? $question : '📎'),
                'startedAt' => now()->toIso8601String(),
            ])
            ->take(-self::LIMIT)
            ->values()
            ->all();
        Cache::put($this->key($user), $asking, self::ASKING_TTL);

        return $token;
    }

    public function end(User $user, string $token): void
    {
        $asking = array_values(array_filter($this->askingRaw($user), fn (array $a) => $a['token'] !== $token));
        $asking === [] ? Cache::forget($this->key($user)) : Cache::put($this->key($user), $asking, self::ASKING_TTL);
    }

    /** The person has seen the chat (opened it, or read its answer as it came). */
    public function markRead(AssistantConversation $conversation): void
    {
        // Not touching updated_at: the history stays in order of questions.
        $conversation->timestamps = false;
        $conversation->forceFill(['read_at' => now()])->save();
        $conversation->timestamps = true;
    }

    /**
     * @return array{chats: list<array<string, mixed>>, asking: list<array<string, mixed>>, unread: int}
     */
    public function for(User $user): array
    {
        $latest = fn (string $column) => AssistantConversation::query()
            ->of($user)
            ->whereNotNull($column)
            ->latest($column)
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        /** @var Collection<int, AssistantConversation> $chats */
        $chats = $latest('answered_at')->concat($latest('read_at'))
            ->unique('id')
            ->sortByDesc(fn (AssistantConversation $c) => $this->activeAt($c)?->getTimestamp() ?? 0)
            ->take(self::LIMIT)
            ->values();

        $unread = AssistantConversation::query()
            ->of($user)
            ->whereNotNull('answered_at')
            ->where(fn ($q) => $q->whereNull('read_at')->orWhereColumn('answered_at', '>', 'read_at'))
            ->count();

        return [
            'chats' => $chats->map(fn (AssistantConversation $c) => $this->present($c))->all(),
            'asking' => array_map(fn (array $a) => [
                ...array_diff_key($a, ['token' => true]),
                ...$this->who($a['agent']),
            ], $this->askingRaw($user)),
            'unread' => $unread,
        ];
    }

    /** @return array<string, mixed> */
    private function present(AssistantConversation $conversation): array
    {
        $last = fn (string $role) => AssistantMessage::query()
            ->where('assistant_conversation_id', $conversation->id)
            ->where('role', $role)
            ->latest('id')
            ->first(['id', 'content', 'refused']);
        $question = $last('user');
        $answer = $last('assistant');

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'agent' => $conversation->agent_key,
            ...$this->who($conversation->agent_key),
            'question' => $question ? $this->excerpt($question->content !== '' ? $question->content : '📎') : null,
            'answer' => $answer && ! $answer->refused ? $this->excerpt($answer->content) : null,
            'unread' => $conversation->answered_at !== null
                && ($conversation->read_at === null || $conversation->answered_at->gt($conversation->read_at)),
            'answeredAt' => $conversation->answered_at?->toIso8601String(),
            'activeAt' => $this->activeAt($conversation)?->toIso8601String(),
        ];
    }

    /** @return array{name: string, role: string} */
    private function who(?string $agentKey): array
    {
        $agent = AgentCatalog::find($agentKey);

        return $agent
            ? ['name' => $agent->persona()['hy'], 'role' => $agent->role()['hy']]
            : ['name' => 'AI օգնական', 'role' => 'Ընդհանուր հարցեր'];
    }

    private function activeAt(AssistantConversation $conversation): ?CarbonInterface
    {
        $answered = $conversation->answered_at;
        $read = $conversation->read_at;
        if (! $answered || ! $read) {
            return $answered ?? $read;
        }

        return $answered->gt($read) ? $answered : $read;
    }

    /** The answer's opening words, plain: no markdown marks. */
    private function excerpt(string $text): string
    {
        $plain = trim(preg_replace(['/\*\*|__|^#+\s*|^\s*[-*•]\s+/mu', '/\s+/u'], ['', ' '], $text));

        return Str::limit($plain, self::EXCERPT, '…');
    }

    /** @return list<array{token: string, agent: ?string, conversationId: ?int, question: string, startedAt: string}> */
    private function askingRaw(User $user): array
    {
        $value = Cache::get($this->key($user));

        return is_array($value) ? $value : [];
    }

    private function key(User $user): string
    {
        return "assistant-asking:{$this->tenants->id()}:{$user->id}";
    }
}

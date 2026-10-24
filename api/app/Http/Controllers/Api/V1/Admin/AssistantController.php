<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Assistant\AssistantFailure;
use App\Assistant\BusinessAssistant;
use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The AI assistant's chat. A question is asked inside a conversation that
 * is kept (per person and business): the earlier turns come from the
 * database, and the question and the answer are saved once answered, so
 * the person can read and continue it later. A failed question saves
 * nothing and costs no request.
 */
class AssistantController extends Controller
{
    /** Conversations listed at most, the latest first. */
    private const MAX_CONVERSATIONS = 100;

    /** Older turns are dropped: the figures are re-sent with every question. */
    private const MAX_MESSAGES = 20;

    public function show(BusinessAssistant $assistant): JsonResponse
    {
        return response()->json(['data' => [
            'configured' => $assistant->isConfigured(),
            ...$assistant->usage(),
            // What to offer when the requests run out (bigger plans).
            'offer' => $assistant->offer(),
        ]]);
    }

    public function store(Request $request, BusinessAssistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            // A question in a kept conversation (a new one without an id)…
            'question' => ['required_without:messages', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
            // …or the whole chat sent by the client, kept nowhere.
            'messages' => ['required_without:question', 'array', 'min:1', 'max:100'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        $conversation = null;
        if (isset($validated['question'])) {
            $conversation = filled($validated['conversation_id'] ?? null)
                ? $this->own($request, AssistantConversation::query()->findOrFail($validated['conversation_id']))
                : null;
            $earlier = $conversation
                ? $conversation->messages()->get(['role', 'content'])->map(fn (AssistantMessage $m) => ['role' => $m->role, 'content' => $m->content])->all()
                : [];
            $validated['messages'] = [...$earlier, ['role' => 'user', 'content' => $validated['question']]];
        }

        if (! $assistant->isConfigured()) {
            return response()->json(['message' => 'The AI assistant is not connected yet.', 'code' => 'not_configured'], 503);
        }

        if ($assistant->limitReached()) {
            return response()->json([
                'message' => 'This subscription month\'s AI assistant requests are used up.',
                'code' => 'limit_reached',
                'usage' => $assistant->usage(),
                'offer' => $assistant->offer(),
            ], 429);
        }

        $messages = $this->trim($validated['messages']);
        if ($messages === []) {
            return response()->json(['message' => 'The last message must be a question.'], 422);
        }

        try {
            $reply = $assistant->ask($messages);
        } catch (APIException $e) {
            $failure = AssistantFailure::from($e);
            Log::warning('AI assistant request failed', [
                'code' => $failure->code,
                'status' => $e instanceof APIStatusException ? $e->status : null,
                'detail' => $failure->detail,
                'model' => config('services.anthropic.model'),
            ]);

            // The API's own words, for whoever sets the assistant up.
            return response()->json([
                'message' => $failure->message,
                'code' => $failure->code,
                'detail' => $failure->detail,
            ], $failure->status);
        }

        if (isset($validated['question'])) {
            $conversation = DB::transaction(function () use ($request, $conversation, $validated, $reply) {
                $conversation ??= AssistantConversation::create([
                    'user_id' => $request->user()->id,
                    'title' => AssistantConversation::titleFrom($validated['question']),
                ]);
                $conversation->messages()->create(['role' => 'user', 'content' => $validated['question']]);
                $conversation->messages()->create(['role' => 'assistant', 'content' => $reply->text, 'refused' => $reply->refused]);
                $conversation->touch();

                return $conversation;
            });
        }

        return response()->json(['data' => [
            'reply' => $reply->text,
            'refused' => $reply->refused,
            'conversation' => $conversation ? $this->summary($conversation->loadCount('messages')) : null,
            ...$assistant->usage(),
        ]]);
    }

    /** The person's conversations in this business, the latest first. */
    public function conversations(Request $request): JsonResponse
    {
        $conversations = AssistantConversation::query()
            ->of($request->user())
            ->withCount('messages')
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::MAX_CONVERSATIONS)
            ->get();

        return response()->json(['data' => $conversations->map(fn (AssistantConversation $c) => $this->summary($c))->values()]);
    }

    /** One conversation with its questions and answers, to read or continue. */
    public function conversation(Request $request, AssistantConversation $assistantConversation): JsonResponse
    {
        $conversation = $this->own($request, $assistantConversation)->loadCount('messages');

        return response()->json(['data' => [
            ...$this->summary($conversation),
            'messages' => $conversation->messages()->get()->map(fn (AssistantMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'refused' => $m->refused,
                'createdAt' => $m->created_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    public function rename(Request $request, AssistantConversation $assistantConversation): JsonResponse
    {
        $conversation = $this->own($request, $assistantConversation);
        $validated = $request->validate(['title' => ['required', 'string', 'max:200']]);
        $conversation->update(['title' => trim($validated['title'])]);

        return response()->json(['data' => $this->summary($conversation->loadCount('messages'))]);
    }

    public function destroy(Request $request, AssistantConversation $assistantConversation): Response
    {
        $this->own($request, $assistantConversation)->delete();

        return response()->noContent();
    }

    /** Another person's conversation behaves as if it doesn't exist. */
    private function own(Request $request, AssistantConversation $conversation): AssistantConversation
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);

        return $conversation;
    }

    /** @return array<string, mixed> */
    private function summary(AssistantConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => (int) ($conversation->messages_count ?? 0),
            'createdAt' => $conversation->created_at?->toIso8601String(),
            'updatedAt' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The last turns, starting with a question and ending with one.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    private function trim(array $messages): array
    {
        $messages = array_values(array_map(
            fn (array $m) => ['role' => $m['role'], 'content' => $m['content']],
            array_slice($messages, -self::MAX_MESSAGES),
        ));

        while ($messages !== [] && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        if ($messages === [] || end($messages)['role'] !== 'user') {
            return [];
        }

        return $messages;
    }
}

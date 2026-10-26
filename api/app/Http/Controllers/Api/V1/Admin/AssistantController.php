<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Agents\AgentCatalog;
use App\Agents\Agents;
use App\Assistant\AssistantFailure;
use App\Assistant\Attachments;
use App\Assistant\BusinessAssistant;
use App\Assistant\ReplyActions;
use App\Http\Controllers\Controller;
use App\Models\AssistantAttachment;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The AI assistant's chat. A question is asked inside a conversation that
 * is kept (per person and business): the earlier turns come from the
 * database, and the question and the answer are saved once answered, so
 * the person can read and continue it later. A failed question saves
 * nothing and costs no request.
 *
 * A conversation is with the assistant or with one of the business's AI
 * agents (agent_key): its first question names the agent, the rest follow
 * it. An agent the business doesn't have answers 403 agent_inactive.
 *
 * A question may carry files uploaded before it (AssistantAttachmentController,
 * at most Attachments::MAX_PER_MESSAGE): they are tied to it once answered
 * and sent to the model again with later questions (the newest few).
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

    public function store(Request $request, BusinessAssistant $assistant, Agents $agents, TenantContext $tenants, Attachments $attachments, ReplyActions $actions): JsonResponse
    {
        $validated = $request->validate([
            // One of the business's AI agents, for a new conversation.
            'agent' => ['nullable', 'string', Rule::in(AgentCatalog::keys())],
            // A question in a kept conversation (a new one without an id),
            // with files or only files…
            'question' => ['required_without_all:messages,attachments', 'nullable', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array', 'max:'.Attachments::MAX_PER_MESSAGE, 'prohibits:messages'],
            'attachments.*' => ['integer', 'distinct'],
            // …or the whole chat sent by the client, kept nowhere.
            'messages' => ['required_without_all:question,attachments', 'array', 'min:1', 'max:100'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        $kept = ! isset($validated['messages']);
        $conversation = null;
        $files = collect();
        if ($kept) {
            $question = trim((string) ($validated['question'] ?? ''));
            $files = $this->pendingFiles($request, $validated['attachments'] ?? []);
            if ($question === '' && $files->isEmpty()) {
                throw ValidationException::withMessages(['question' => ['Գրեք հարցը կամ կցեք ֆայլ։']]);
            }
            $conversation = filled($validated['conversation_id'] ?? null)
                ? $this->own($request, AssistantConversation::query()->findOrFail($validated['conversation_id']))
                : null;
            $earlier = $conversation
                ? $conversation->messages()->with('attachments')->get()->map(fn (AssistantMessage $m) => [
                    'role' => $m->role,
                    'content' => $m->content,
                    'files' => $m->attachments->all(),
                ])->all()
                : [];
            $validated['messages'] = [...$earlier, ['role' => 'user', 'content' => $question, 'files' => $files->all()]];
        }

        // A kept conversation stays with the agent it started with.
        $agent = AgentCatalog::find($conversation ? $conversation->agent_key : ($validated['agent'] ?? null));
        if ($agent && ! $agents->isActive($tenants->current(), $agent)) {
            return response()->json([
                'message' => 'This AI agent is not on your team yet.',
                'code' => 'agent_inactive',
                'agent' => $agent->key(),
            ], 403);
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

        $actions->reset();
        try {
            $reply = $assistant->ask($attachments->forModel($messages), $agent, $request->user());
        } catch (APIException $e) {
            $failure = AssistantFailure::from($e);
            Log::warning('AI assistant request failed', [
                'code' => $failure->code,
                'status' => $e instanceof APIStatusException ? $e->status : null,
                'detail' => $failure->detail,
                'model' => config('services.anthropic.model'),
                'agent' => $agent?->key(),
            ]);

            // The API's own words, for whoever sets the assistant up.
            return response()->json([
                'message' => $failure->message,
                'code' => $failure->code,
                'detail' => $failure->detail,
            ], $failure->status);
        }

        if ($kept) {
            $conversation = DB::transaction(function () use ($request, $conversation, $question, $files, $reply, $agent, $actions) {
                $conversation ??= AssistantConversation::create([
                    'user_id' => $request->user()->id,
                    'agent_key' => $agent?->key(),
                    'title' => AssistantConversation::titleFrom($question !== '' ? $question : $files->first()->name),
                ]);
                $asked = $conversation->messages()->create(['role' => 'user', 'content' => $question]);
                if ($files->isNotEmpty()) {
                    AssistantAttachment::query()->whereKey($files->modelKeys())->update(['assistant_message_id' => $asked->id]);
                }
                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $reply->text,
                    'refused' => $reply->refused,
                    'actions' => $actions->all() ?: null,
                ]);
                $conversation->touch();

                return $conversation;
            });
        }

        return response()->json(['data' => [
            'reply' => $reply->text,
            'refused' => $reply->refused,
            // Cards under the answer: a reshape to apply, a file to download.
            'actions' => $actions->all(),
            // The files as the question now holds them.
            'attachments' => $files->map(fn (AssistantAttachment $file) => $file->present())->values(),
            'conversation' => $conversation ? $this->summary($conversation->loadCount('messages')) : null,
            ...$assistant->usage(),
        ]]);
    }

    /**
     * The person's conversations in this business, the latest first: with
     * the assistant, or with one agent (?agent=analyst).
     */
    public function conversations(Request $request): JsonResponse
    {
        $agent = AgentCatalog::find($request->query('agent') ?: null);
        abort_if(filled($request->query('agent')) && ! $agent, 404);

        $conversations = AssistantConversation::query()
            ->of($request->user())
            ->where(fn ($q) => $agent ? $q->where('agent_key', $agent->key()) : $q->whereNull('agent_key'))
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
            'messages' => $conversation->messages()->with('attachments')->get()->map(fn (AssistantMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'refused' => $m->refused,
                'actions' => $m->actions ?? [],
                'attachments' => $m->attachments->map(fn (AssistantAttachment $file) => $file->present())->values(),
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

    public function destroy(Request $request, AssistantConversation $assistantConversation, Attachments $attachments): Response
    {
        $conversation = $this->own($request, $assistantConversation);
        // Its files go with it (the rows by the foreign keys, the files here).
        $paths = AssistantAttachment::query()
            ->whereIn('assistant_message_id', $conversation->messages()->select('id'))
            ->pluck('path')
            ->all();
        $conversation->delete();
        $attachments->delete($paths);

        return response()->noContent();
    }

    /**
     * The person's uploaded files not sent yet, by the ids given (all of
     * them, or the question is refused).
     *
     * @param  list<int>  $ids
     * @return Collection<int, AssistantAttachment>
     */
    private function pendingFiles(Request $request, array $ids): Collection
    {
        $files = AssistantAttachment::query()
            ->of($request->user())
            ->whereNull('assistant_message_id')
            ->whereKey($ids)
            ->orderBy('id')
            ->get();

        if ($files->count() !== count($ids)) {
            throw ValidationException::withMessages(['attachments' => ['Ֆայլը չի գտնվել։ Կցեք այն կրկին։']]);
        }

        return $files;
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
            'agent' => $conversation->agent_key,
            'messages' => (int) ($conversation->messages_count ?? 0),
            'createdAt' => $conversation->created_at?->toIso8601String(),
            'updatedAt' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The last turns, starting with a question and ending with one.
     *
     * @param  list<array{role: string, content: string, files?: list<AssistantAttachment>}>  $messages
     * @return list<array{role: 'user'|'assistant', content: string, files: list<AssistantAttachment>}>
     */
    private function trim(array $messages): array
    {
        $messages = array_values(array_map(
            fn (array $m) => ['role' => $m['role'], 'content' => $m['content'], 'files' => $m['files'] ?? []],
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

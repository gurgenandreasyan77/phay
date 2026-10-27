<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Agents\Agent;
use App\Agents\AgentCatalog;
use App\Agents\Agents;
use App\Assistant\AssistantFailure;
use App\Assistant\Attachments;
use App\Assistant\BusinessAssistant;
use App\Assistant\RecentChats;
use App\Assistant\ReplyActions;
use App\Http\Controllers\Controller;
use App\Media\DesignStudio;
use App\Media\VideoStudio;
use App\Models\AssistantAttachment;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\VideoJob;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection as SupportCollection;
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
 *
 * Pictures an agent makes while answering (DesignStudio) are kept as files
 * of the answer and come back with it ("designs"); videos it cuts
 * (VideoStudio) render for a minute or two after the answer: they come
 * back as "videos" and the chat asks about each (video) until it is ready.
 *
 * While a question is answered it is "asking" (RecentChats), and the answer
 * is kept even when the person leaves the page meanwhile: the header then
 * shows it as unread until they open the chat (recent, read).
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

    public function store(Request $request, BusinessAssistant $assistant, Agents $agents, TenantContext $tenants, Attachments $attachments, ReplyActions $actions, DesignStudio $studio, VideoStudio $videoStudio, RecentChats $recent): JsonResponse
    {
        // Leaving the page mid-answer doesn't lose the answer: it is kept and shown as new.
        ignore_user_abort(true);

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
                ? $conversation->messages()->with(['attachments', 'videoJobs'])->get()->map(fn (AssistantMessage $m) => [
                    'role' => $m->role,
                    'content' => $m->content,
                    'files' => $m->attachments->all(),
                    'videos' => $m->videoJobs->all(),
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
        $asking = $kept ? $recent->begin($request->user(), $agent?->key(), $conversation, $question) : null;
        try {
            return $this->answer($request, $assistant, $attachments, $actions, $studio, $videoStudio, $messages, $agent, $kept, $conversation, $question ?? '', $files);
        } finally {
            if ($asking) {
                $recent->end($request->user(), $asking);
            }
        }
    }

    /**
     * Asks the model and keeps the question and the answer.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  SupportCollection<int, AssistantAttachment>  $files
     */
    private function answer(
        Request $request,
        BusinessAssistant $assistant,
        Attachments $attachments,
        ReplyActions $actions,
        DesignStudio $studio,
        VideoStudio $videoStudio,
        array $messages,
        ?Agent $agent,
        bool $kept,
        ?AssistantConversation $conversation,
        string $question,
        SupportCollection $files,
    ): JsonResponse
    {
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

        $designs = collect($studio->produced());
        $videos = collect($videoStudio->started());

        if ($kept) {
            $conversation = DB::transaction(function () use ($request, $conversation, $question, $files, $reply, $agent, $actions, $designs, $videos) {
                $conversation ??= AssistantConversation::create([
                    'user_id' => $request->user()->id,
                    'agent_key' => $agent?->key(),
                    'title' => AssistantConversation::titleFrom($question !== '' ? $question : $files->first()->name),
                ]);
                $asked = $conversation->messages()->create(['role' => 'user', 'content' => $question]);
                if ($files->isNotEmpty()) {
                    AssistantAttachment::query()->whereKey($files->modelKeys())->update(['assistant_message_id' => $asked->id]);
                }
                $answer = $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $reply->text,
                    'refused' => $reply->refused,
                    'actions' => $actions->all() ?: null,
                ]);
                if ($designs->isNotEmpty()) {
                    AssistantAttachment::query()->whereKey($designs->pluck('id'))->update(['assistant_message_id' => $answer->id]);
                }
                if ($videos->isNotEmpty()) {
                    VideoJob::query()->whereKey($videos->pluck('id'))->update(['assistant_message_id' => $answer->id]);
                    // One collected already (a very short one) belongs to the answer too.
                    AssistantAttachment::query()->whereIn('id', VideoJob::query()->whereKey($videos->pluck('id'))->whereNotNull('attachment_id')->select('attachment_id'))
                        ->update(['assistant_message_id' => $answer->id]);
                }
                // Answered: new for its person until they see it (read).
                $conversation->forceFill(['answered_at' => now()])->save();
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
            // The pictures the agent made in its answer.
            'designs' => $designs->map(fn (AssistantAttachment $file) => $file->present())->values(),
            // The videos it started cutting (rendering: ask GET assistant/videos/{id}).
            'videos' => $videos->map(fn (VideoJob $video) => $video->present())->values(),
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
    public function conversation(Request $request, AssistantConversation $assistantConversation, RecentChats $recent): JsonResponse
    {
        $conversation = $this->own($request, $assistantConversation)->loadCount('messages');
        // Opened: its answers are seen, and it is the chat to come back to.
        $recent->markRead($conversation);

        return response()->json(['data' => [
            ...$this->summary($conversation),
            'messages' => $conversation->messages()->with(['attachments', 'videoJobs.attachment'])->get()->map(fn (AssistantMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'refused' => $m->refused,
                'actions' => $m->actions ?? [],
                // A video's file comes with its video.
                'attachments' => $m->attachments->where('origin', '!=', AssistantAttachment::VIDEO)->map(fn (AssistantAttachment $file) => $file->present())->values(),
                'videos' => $m->videoJobs->map(fn (VideoJob $video) => $video->present())->values(),
                'createdAt' => $m->created_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    /**
     * The person's latest chats in this business, with what was asked and
     * answered, which answers are new and which questions are being answered
     * now: for the header (a floating button on a phone) and for reopening
     * the last chat.
     */
    public function recent(Request $request, RecentChats $recent): JsonResponse
    {
        return response()->json(['data' => $recent->for($request->user())]);
    }

    /** The chat's answer was seen as it came (the chat was open). */
    public function read(Request $request, AssistantConversation $assistantConversation, RecentChats $recent): Response
    {
        $recent->markRead($this->own($request, $assistantConversation));

        return response()->noContent();
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
            ->get(['id', 'path', 'meta'])
            ->flatMap(fn (AssistantAttachment $a) => $a->paths())
            ->all();
        $conversation->delete();
        $attachments->delete($paths);

        return response()->noContent();
    }

    /**
     * A video an agent is cutting, as the chat asks about it until it is
     * ready (asking also collects it when it is).
     */
    public function video(Request $request, VideoJob $videoJob, VideoStudio $videoStudio): JsonResponse
    {
        abort_unless((int) $videoJob->user_id === (int) $request->user()->id, 404);

        $video = $videoStudio->refresh($videoJob);

        return response()->json(['data' => [
            ...$video->loadMissing('attachment')->present(),
            'progress' => $videoStudio->progress($video),
        ]]);
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
            ->uploads()
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
     * @param  list<array{role: string, content: string, files?: list<AssistantAttachment>, videos?: list<VideoJob>}>  $messages
     * @return list<array{role: 'user'|'assistant', content: string, files: list<AssistantAttachment>, videos: list<VideoJob>}>
     */
    private function trim(array $messages): array
    {
        $messages = array_values(array_map(
            fn (array $m) => ['role' => $m['role'], 'content' => $m['content'], 'files' => $m['files'] ?? [], 'videos' => $m['videos'] ?? []],
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

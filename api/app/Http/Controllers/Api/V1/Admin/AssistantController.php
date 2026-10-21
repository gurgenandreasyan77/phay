<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Anthropic\Core\Exceptions\APIException;
use App\Assistant\BusinessAssistant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssistantController extends Controller
{
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
            'messages' => ['required', 'array', 'min:1', 'max:100'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

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
            Log::warning('AI assistant request failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'The AI assistant is unavailable right now. Try again in a minute.', 'code' => 'unavailable'], 502);
        }

        return response()->json(['data' => [
            'reply' => $reply->text,
            'refused' => $reply->refused,
            ...$assistant->usage(),
        ]]);
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

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** What visitors wrote through the site's feedback form, newest first. */
class FeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $messages = FeedbackMessage::query()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest('id')
            ->paginate(30);

        return response()->json([
            'data' => collect($messages->items())->map($this->present(...)),
            'meta' => [
                'total' => $messages->total(),
                'currentPage' => $messages->currentPage(),
                'lastPage' => $messages->lastPage(),
                'unread' => FeedbackMessage::query()->whereNull('read_at')->count(),
            ],
        ]);
    }

    /** Marks the message read or unread. */
    public function update(Request $request, FeedbackMessage $feedbackMessage): JsonResponse
    {
        $validated = $request->validate(['read' => ['required', 'boolean']]);
        $feedbackMessage->update(['read_at' => $validated['read'] ? ($feedbackMessage->read_at ?? now()) : null]);

        return response()->json(['data' => $this->present($feedbackMessage)]);
    }

    public function destroy(FeedbackMessage $feedbackMessage): Response
    {
        $feedbackMessage->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function present(FeedbackMessage $message): array
    {
        return [
            'id' => $message->id,
            'name' => $message->name,
            'phone' => $message->phone,
            'email' => $message->email,
            'message' => $message->message,
            'locale' => $message->locale,
            'readAt' => $message->read_at?->toIso8601String(),
            'createdAt' => $message->created_at?->toIso8601String(),
        ];
    }
}

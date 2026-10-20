<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Models\FeedbackMessage;
use App\Notifications\Notifier;
use App\Rules\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The feedback form of the business's site (the site module). */
class FeedbackController extends Controller
{
    public function store(Request $request, Notifier $notifier): JsonResponse
    {
        // Honeypot, as on the order form: bots get a normal answer, nothing is kept.
        if ($request->filled('website')) {
            return response()->json(['data' => ['status' => 'received']], 201);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30', new Phone],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'locale' => ['nullable', Rule::enum(Locale::class)],
        ]);

        $message = FeedbackMessage::create($validated);
        $notifier->feedback($message);

        return response()->json(['data' => ['status' => 'received']], 201);
    }
}

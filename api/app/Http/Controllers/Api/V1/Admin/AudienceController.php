<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Audience\Audience;
use App\Http\Controllers\Controller;
use App\Services\BusinessAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Who buys and from where: channels, gender, age groups and social accounts. */
class AudienceController extends Controller
{
    public function __invoke(Request $request, Audience $audience): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'integer', Rule::in(BusinessAnalytics::PERIODS)],
        ]);

        return response()->json(['data' => $audience->summary((int) ($validated['period'] ?? 30))]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Platform\BusinessOverview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A business's page on the platform level (platform staff): its activity,
 * subscription, modules, agents and AI usage, never its own data.
 */
class PlatformBusinessController extends Controller
{
    public function show(Request $request, Tenant $tenant, BusinessOverview $overview): JsonResponse
    {
        return response()->json(['data' => $overview->for($tenant, $request->user())]);
    }
}

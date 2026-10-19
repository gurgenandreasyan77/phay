<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Analytics\Home;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LeadResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business admin's home: today's and this month's orders and sales,
 * net profit, what's coming up and the site's orders (see Home).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, Home $home): JsonResponse
    {
        $data = $home->for($request->user());

        if ($data['site'] !== null) {
            $data['site']['recent'] = LeadResource::collection($data['site']['recent'])->resolve($request);
        }

        return response()->json(['data' => $data]);
    }
}

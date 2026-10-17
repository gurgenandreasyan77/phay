<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $countsByStatus = collect(LeadStatus::cases())->mapWithKeys(
            fn (LeadStatus $status) => [$status->value => Lead::query()->where('status', $status)->count()]
        );

        return response()->json([
            'data' => [
                'countsByStatus' => $countsByStatus,
                'newToday' => Lead::query()->whereDate('created_at', Date::today())->count(),
                'newThisWeek' => Lead::query()->where('created_at', '>=', Date::now()->startOfWeek())->count(),
                'recentLeads' => LeadResource::collection(
                    Lead::query()->with('service')->orderByDesc('created_at')->limit(10)->get()
                ),
            ],
        ]);
    }
}

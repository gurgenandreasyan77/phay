<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PageStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MarketTaskPageResource;
use App\Models\MarketTaskPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskPageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $pages = MarketTaskPage::query()
            ->where('status', PageStatus::Published)
            ->with(['products' => function ($query) {
                $query->where('status', ProductStatus::Published);
            }])
            ->orderBy('sort_order')
            ->get();

        return MarketTaskPageResource::collection($pages);
    }

    public function show(string $slug): JsonResponse
    {
        $field = MarketTaskPage::slugColumn(app()->getLocale());

        $page = MarketTaskPage::query()
            ->where($field, $slug)
            ->where('status', PageStatus::Published)
            ->with(['relatedService', 'products' => function ($query) {
                $query->where('status', ProductStatus::Published);
            }])
            ->first();

        if (! $page) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return (new MarketTaskPageResource($page))->response();
    }
}

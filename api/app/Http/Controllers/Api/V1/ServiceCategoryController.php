<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ServiceCategory::query()
            ->with(['services' => function ($query) {
                $query->whereIn('status', array_map(fn ($s) => $s->value, ServiceStatus::publicallyVisible()))
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return ServiceCategoryResource::collection($categories);
    }
}

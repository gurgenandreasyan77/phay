<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdditionalServiceResource;
use App\Models\AdditionalService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdditionalServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $items = AdditionalService::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return AdditionalServiceResource::collection($items);
    }
}

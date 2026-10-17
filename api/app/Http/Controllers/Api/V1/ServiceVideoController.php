<?php

namespace App\Http\Controllers\Api\V1;

use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceVideoResource;
use App\Models\ServiceVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Pennant\Feature;

class ServiceVideoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $videos = ServiceVideo::query()
            ->published()
            ->when($request->query('service'), function ($query, $serviceSlug) {
                $query->whereHas('service', fn ($q) => $q->where('slug', $serviceSlug));
            })
            ->when(! Feature::globally()->active(FeatureFlag::AutoCare->value), function ($query) {
                $query->whereDoesntHave('service.category', fn ($q) => $q->where('slug', FeatureFlag::AutoCare->value));
            })
            ->orderBy('sort_order')
            ->get();

        return ServiceVideoResource::collection($videos);
    }
}

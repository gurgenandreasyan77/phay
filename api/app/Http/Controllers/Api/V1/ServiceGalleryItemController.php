<?php

namespace App\Http\Controllers\Api\V1;

use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceGalleryItemResource;
use App\Models\ServiceGalleryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Pennant\Feature;

class ServiceGalleryItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = ServiceGalleryItem::query()
            ->publiclyVisible()
            ->with('service')
            ->when($request->query('service'), function ($query, $serviceSlug) {
                $query->whereHas('service', fn ($q) => $q->where('slug', $serviceSlug));
            })
            ->when(! Feature::globally()->active(FeatureFlag::AutoCare->value), function ($query) {
                $query->whereDoesntHave('service.category', fn ($q) => $q->where('slug', FeatureFlag::AutoCare->value));
            })
            ->orderBy('sort_order')
            ->get();

        return ServiceGalleryItemResource::collection($items);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceGalleryItemResource;
use App\Models\ServiceGalleryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
            ->orderBy('sort_order')
            ->get();

        return ServiceGalleryItemResource::collection($items);
    }
}

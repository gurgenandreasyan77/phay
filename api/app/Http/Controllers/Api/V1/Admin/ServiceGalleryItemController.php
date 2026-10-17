<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceGalleryItemRequest;
use App\Http\Requests\Admin\UpdateServiceGalleryItemRequest;
use App\Http\Resources\Admin\ServiceGalleryItemResource;
use App\Models\ServiceGalleryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServiceGalleryItemController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $items = ServiceGalleryItem::query()->with('service')->orderBy('sort_order')->get();

        return ServiceGalleryItemResource::collection($items);
    }

    public function store(StoreServiceGalleryItemRequest $request): JsonResponse
    {
        $item = ServiceGalleryItem::create($request->validated());

        return (new ServiceGalleryItemResource($item->fresh()))->response()->setStatusCode(201);
    }

    public function update(UpdateServiceGalleryItemRequest $request, ServiceGalleryItem $serviceGalleryItem): JsonResponse
    {
        $serviceGalleryItem->update($request->validated());

        return (new ServiceGalleryItemResource($serviceGalleryItem->fresh()))->response();
    }

    public function destroy(ServiceGalleryItem $serviceGalleryItem): Response
    {
        $serviceGalleryItem->delete();

        return response()->noContent();
    }
}

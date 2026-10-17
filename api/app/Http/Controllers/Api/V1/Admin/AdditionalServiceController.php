<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdditionalServiceRequest;
use App\Http\Requests\Admin\UpdateAdditionalServiceRequest;
use App\Http\Resources\Admin\AdditionalServiceResource;
use App\Models\AdditionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdditionalServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $items = AdditionalService::query()->orderBy('sort_order')->get();

        return AdditionalServiceResource::collection($items);
    }

    public function store(StoreAdditionalServiceRequest $request): JsonResponse
    {
        $item = AdditionalService::create($request->validated());

        return (new AdditionalServiceResource($item->fresh()))->response()->setStatusCode(201);
    }

    public function update(UpdateAdditionalServiceRequest $request, AdditionalService $additionalService): JsonResponse
    {
        $additionalService->update($request->validated());

        return (new AdditionalServiceResource($additionalService->fresh()))->response();
    }

    public function destroy(AdditionalService $additionalService): Response
    {
        $additionalService->delete();

        return response()->noContent();
    }
}

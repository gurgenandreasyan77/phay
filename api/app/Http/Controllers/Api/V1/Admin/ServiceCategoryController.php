<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceCategoryRequest;
use App\Http\Requests\Admin\UpdateServiceCategoryRequest;
use App\Http\Resources\Admin\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServiceCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ServiceCategory::query()
            ->withCount('services')
            ->orderBy('sort_order')
            ->get();

        return ServiceCategoryResource::collection($categories);
    }

    public function store(StoreServiceCategoryRequest $request): JsonResponse
    {
        $category = ServiceCategory::create($request->validated());

        return (new ServiceCategoryResource($category->fresh()))->response()->setStatusCode(201);
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $serviceCategory->update($request->validated());

        return (new ServiceCategoryResource($serviceCategory->fresh()))->response();
    }

    public function destroy(ServiceCategory $serviceCategory): Response
    {
        $serviceCategory->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductCategoryRequest;
use App\Http\Requests\Admin\UpdateProductCategoryRequest;
use App\Http\Resources\Admin\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ProductCategory::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return ProductCategoryResource::collection($categories);
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $category = ProductCategory::create($request->validated());

        return (new ProductCategoryResource($category->fresh()))->response()->setStatusCode(201);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): JsonResponse
    {
        $productCategory->update($request->validated());

        return (new ProductCategoryResource($productCategory->fresh()))->response();
    }

    public function destroy(ProductCategory $productCategory): Response
    {
        $productCategory->delete();

        return response()->noContent();
    }
}

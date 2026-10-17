<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ProductCategory::query()
            ->whereHas('products', fn ($q) => $q->where('status', ProductStatus::Published))
            ->orderBy('sort_order')
            ->get();

        return ProductCategoryResource::collection($categories);
    }

    public function show(string $slug): JsonResponse
    {
        $locale = app()->getLocale();
        $field = ProductCategory::slugColumn($locale);

        $category = ProductCategory::query()->where($field, $slug)->first();

        if (! $category) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $products = $category->products()
            ->where('status', ProductStatus::Published)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => array_merge(
                (new ProductCategoryResource($category))->resolve(),
                ['products' => ProductSummaryResource::collection($products)],
            ),
        ]);
    }
}

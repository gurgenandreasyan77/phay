<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AvailabilityStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSlugRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $locale = app()->getLocale();
        $categoryField = ProductCategory::slugColumn($locale);

        $items = Product::query()
            ->where('status', ProductStatus::Published)
            ->with(['categories', 'images'])
            ->when($request->query('category'), fn ($q, $slug) => $q->whereHas(
                'categories',
                fn ($category) => $category->where($categoryField, $slug),
            ))
            ->when($request->query('brand'), fn ($q, $brand) => $q->where('brand', $brand))
            ->when($request->query('composition_type'), fn ($q, $type) => $q->where('composition_type', $type))
            ->when($request->has('requires_extractor'), fn ($q) => $q->where(
                'requires_extractor',
                $request->boolean('requires_extractor'),
            ))
            ->when($request->boolean('in_stock'), fn ($q) => $q->where(
                'availability_status',
                AvailabilityStatus::InStock,
            ))
            ->orderBy('sort_order')
            ->get();

        return ProductSummaryResource::collection($items);
    }

    /**
     * Product detail, resolved by locale-aware slug. On a miss, checks
     * `product_slug_redirects` (populated whenever an admin edits a
     * product's slug) and reports the current URL so the frontend can issue
     * a permanent redirect instead of a 404.
     */
    public function show(Request $request, string $categorySlug, string $productSlug): JsonResponse
    {
        $locale = app()->getLocale();
        $slugField = Product::slugColumn($locale);

        $product = Product::query()
            ->where('status', ProductStatus::Published)
            ->where($slugField, $productSlug)
            ->with(['categories', 'images', 'services.category', 'related', 'accessories', 'taskPages'])
            ->first();

        if ($product) {
            return (new ProductResource($product))->response();
        }

        $redirect = ProductSlugRedirect::query()
            ->where('locale', $locale)
            ->where('old_slug', $productSlug)
            ->with('product.categories')
            ->first();

        if ($redirect?->product && $redirect->product->status === ProductStatus::Published) {
            $target = $redirect->product;
            $categoryField = ProductCategory::slugColumn($locale);

            return response()->json([
                'redirect' => [
                    'category' => $target->primaryCategory()?->{$categoryField},
                    'product' => $target->localizedSlug($locale),
                ],
            ]);
        }

        return response()->json(['message' => 'Not found'], 404);
    }
}

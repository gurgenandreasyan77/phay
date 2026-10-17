<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Product;
use App\Models\ProductSlugRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private const RELATION_KEYS = [
        'category_ids',
        'primary_category_id',
        'service_ids',
        'related_product_ids',
        'accessory_product_ids',
        'images',
    ];

    public function index(): AnonymousResourceCollection
    {
        $items = Product::query()
            ->with(['categories', 'images', 'services', 'related', 'accessories'])
            ->orderBy('sort_order')
            ->get();

        return ProductResource::collection($items);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['slug'] = $validated['slug_en'] ?? $validated['slug_hy'];

        $product = DB::transaction(function () use ($validated) {
            $product = Product::create(Arr::except($validated, self::RELATION_KEYS));
            $this->syncRelations($product, $validated);

            return $product;
        });

        return (new ProductResource($this->freshWithRelations($product)))->response()->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $product) {
            $this->recordSlugChanges($product, $validated);
            $product->update(Arr::except($validated, self::RELATION_KEYS));
            $this->syncRelations($product, $validated);
        });

        return (new ProductResource($this->freshWithRelations($product)))->response();
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    /**
     * Whenever an admin changes a locale's slug, keep the old one resolvable
     * via a 301 by recording it in `product_slug_redirects` — see
     * ProductController@show on the public side, which checks this table on
     * a slug-lookup miss.
     */
    private function recordSlugChanges(Product $product, array $validated): void
    {
        foreach (['hy' => 'slug_hy', 'ru' => 'slug_ru', 'en' => 'slug_en'] as $locale => $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $oldSlug = $product->getOriginal($field);
            $newSlug = $validated[$field];

            if ($oldSlug && $oldSlug !== $newSlug) {
                ProductSlugRedirect::query()->firstOrCreate([
                    'locale' => $locale,
                    'old_slug' => $oldSlug,
                ], [
                    'product_id' => $product->id,
                ]);
            }
        }
    }

    private function syncRelations(Product $product, array $validated): void
    {
        if (array_key_exists('category_ids', $validated) || array_key_exists('primary_category_id', $validated)) {
            $categoryIds = $validated['category_ids'] ?? $product->categories->pluck('id')->all();
            $primaryId = $validated['primary_category_id'] ?? $categoryIds[0] ?? null;

            $product->categories()->sync(collect($categoryIds)->mapWithKeys(fn ($id) => [
                $id => ['is_primary' => $id === $primaryId],
            ]));
        }

        if (array_key_exists('service_ids', $validated)) {
            $product->services()->sync($validated['service_ids']);
        }

        if (array_key_exists('related_product_ids', $validated)) {
            $product->related()->sync(collect($validated['related_product_ids'])->mapWithKeys(fn ($id) => [
                $id => ['type' => 'related'],
            ]));
        }

        if (array_key_exists('accessory_product_ids', $validated)) {
            $product->accessories()->sync(collect($validated['accessory_product_ids'])->mapWithKeys(fn ($id) => [
                $id => ['type' => 'accessory'],
            ]));
        }

        if (array_key_exists('images', $validated)) {
            $product->images()->delete();

            foreach ($validated['images'] as $index => $image) {
                $product->images()->create([
                    'path' => $image['path'],
                    'alt' => $image['alt'] ?? null,
                    'is_primary' => $image['is_primary'] ?? false,
                    'sort_order' => $image['sort_order'] ?? $index,
                ]);
            }
        }
    }

    private function freshWithRelations(Product $product): Product
    {
        return $product->fresh(['categories', 'images', 'services', 'related', 'accessories']);
    }
}

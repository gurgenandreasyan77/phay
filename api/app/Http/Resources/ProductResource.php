<?php

namespace App\Http\Resources;

use App\Services\ProductSeoGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $seo = app(ProductSeoGenerator::class);
        $category = $this->primaryCategory();

        return [
            'id' => $this->id,
            'slug' => $this->localizedSlug($locale),
            // See ProductCategoryResource::$slugs — same reasoning, needed
            // for correct per-locale hreflang alternates on the product page.
            'slugs' => ['hy' => $this->slug_hy, 'ru' => $this->slug_ru, 'en' => $this->slug_en],
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'brand' => $this->brand,
            'manufacturer' => $this->manufacturer,
            'countryOfOrigin' => $this->country_of_origin,
            'volume' => $this->volume_value ? ['value' => (float) $this->volume_value, 'unit' => $this->volume_unit] : null,
            'name' => $this->trans('name'),
            'shortDescription' => $this->short_description ? $this->trans('short_description') : null,
            'description' => $this->trans('description'),
            'alt' => $this->alt ? $this->trans('alt') : null,
            'image' => $this->primaryImage()?->path ?? $this->image,
            'images' => $this->images->map(fn ($image) => [
                'path' => $image->path,
                'alt' => $image->alt ? $image->trans('alt') : null,
                'isPrimary' => $image->is_primary,
            ])->values(),
            'priceAmount' => $this->price_amount,
            'oldPriceAmount' => $this->old_price_amount,
            'stockQuantity' => $this->stock_quantity,
            'availabilityStatus' => $this->availability_status?->value,

            'category' => $category ? new ProductCategoryResource($category) : null,
            'categories' => ProductCategoryResource::collection($this->categories),

            'stains' => $this->stains ? $this->transList('stains') : null,
            'surfaces' => $this->surfaces ? $this->transList('surfaces') : null,
            'forbiddenSurfaces' => $this->forbidden_surfaces ? $this->transList('forbidden_surfaces') : null,
            'phValue' => $this->ph_value !== null ? (float) $this->ph_value : null,
            'compositionType' => $this->composition_type?->value,
            'dilutionTable' => $this->dilution_table ? $this->transList('dilution_table') : null,
            'usageSteps' => $this->usage_steps ? $this->transList('usage_steps') : null,
            'requiresExtractor' => $this->requires_extractor,
            'consumptionNote' => $this->consumption_note ? $this->trans('consumption_note') : null,
            'contactTime' => $this->contact_time ? $this->trans('contact_time') : null,
            'rinseMethod' => $this->rinse_method ? $this->trans('rinse_method') : null,
            'safetyNotes' => $this->safety_notes ? $this->transList('safety_notes') : null,
            'storageConditions' => $this->storage_conditions ? $this->trans('storage_conditions') : null,
            'shelfLife' => $this->shelf_life ? $this->trans('shelf_life') : null,
            'sdsUrl' => $this->sds_url,
            'certificateNote' => $this->certificate_note ? $this->trans('certificate_note') : null,

            'seoTitle' => $seo->title($this->resource, $locale),
            'seoDescription' => $seo->description($this->resource, $locale),
            'ogTitle' => $this->og_title ? $this->trans('og_title') : null,
            'ogDescription' => $this->og_description ? $this->trans('og_description') : null,
            'ogImage' => $this->og_image,
            'canonicalOverride' => $this->canonical_override,
            'seoRobots' => $this->seo_robots?->value,

            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'relatedProducts' => ProductSummaryResource::collection($this->whenLoaded('related')),
            'accessories' => ProductSummaryResource::collection($this->whenLoaded('accessories')),
            'taskPages' => $this->whenLoaded('taskPages', fn () => $this->taskPages->map(fn ($page) => [
                'slug' => $page->localizedSlug($locale),
                'title' => $page->trans('title'),
            ])),

            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

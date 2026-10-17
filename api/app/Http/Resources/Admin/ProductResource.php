<?php

namespace App\Http\Resources\Admin;

use App\Services\ProductSeoGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $seo = app(ProductSeoGenerator::class);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'slugHy' => $this->slug_hy,
            'slugRu' => $this->slug_ru,
            'slugEn' => $this->slug_en,
            'status' => $this->status->value,

            'name' => $this->name ?? (object) [],
            'shortDescription' => $this->short_description ?? (object) [],
            'description' => $this->description ?? (object) [],
            'alt' => $this->alt ?? (object) [],
            'image' => $this->image,
            'images' => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'alt' => $image->alt ?? (object) [],
                'isPrimary' => $image->is_primary,
                'sortOrder' => $image->sort_order,
            ])->values(),

            'brand' => $this->brand,
            'manufacturer' => $this->manufacturer,
            'countryOfOrigin' => $this->country_of_origin,
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'volumeValue' => $this->volume_value !== null ? (float) $this->volume_value : null,
            'volumeUnit' => $this->volume_unit,
            'priceAmount' => $this->price_amount,
            'oldPriceAmount' => $this->old_price_amount,
            'stockQuantity' => $this->stock_quantity,
            'availabilityStatus' => $this->availability_status?->value,
            'sortOrder' => $this->sort_order,
            'adminNote' => $this->admin_note,

            'stains' => $this->stains ?? (object) [],
            'surfaces' => $this->surfaces ?? (object) [],
            'forbiddenSurfaces' => $this->forbidden_surfaces ?? (object) [],
            'phValue' => $this->ph_value !== null ? (float) $this->ph_value : null,
            'compositionType' => $this->composition_type?->value,
            'dilutionTable' => $this->dilution_table ?? (object) [],
            'usageSteps' => $this->usage_steps ?? (object) [],
            'requiresExtractor' => $this->requires_extractor,
            'consumptionNote' => $this->consumption_note ?? (object) [],
            'contactTime' => $this->contact_time ?? (object) [],
            'rinseMethod' => $this->rinse_method ?? (object) [],
            'safetyNotes' => $this->safety_notes ?? (object) [],
            'storageConditions' => $this->storage_conditions ?? (object) [],
            'shelfLife' => $this->shelf_life ?? (object) [],
            'batchTracked' => $this->batch_tracked,
            'sdsUrl' => $this->sds_url,
            'certificateNote' => $this->certificate_note ?? (object) [],

            'seoTitle' => $this->seo_title ?? (object) [],
            'seoDescription' => $this->seo_description ?? (object) [],
            'ogTitle' => $this->og_title ?? (object) [],
            'ogDescription' => $this->og_description ?? (object) [],
            'ogImage' => $this->og_image,
            'canonicalOverride' => $this->canonical_override,
            'seoRobots' => $this->seo_robots?->value,
            'primaryKeyword' => $this->primary_keyword,
            'secondaryKeywords' => $this->secondary_keywords ?? [],

            'seoPreview' => [
                'hy' => ['title' => $seo->title($this->resource, 'hy'), 'description' => $seo->description($this->resource, 'hy')],
                'ru' => ['title' => $seo->title($this->resource, 'ru'), 'description' => $seo->description($this->resource, 'ru')],
                'en' => ['title' => $seo->title($this->resource, 'en'), 'description' => $seo->description($this->resource, 'en')],
            ],

            'categoryIds' => $this->categories->pluck('id')->values(),
            'primaryCategoryId' => $this->primaryCategory()?->id,
            'serviceIds' => $this->services->pluck('id')->values(),
            'relatedProductIds' => $this->related->pluck('id')->values(),
            'accessoryProductIds' => $this->accessories->pluck('id')->values(),

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

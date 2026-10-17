<?php

namespace App\Models;

use App\Enums\AvailabilityStatus;
use App\Enums\CompositionType;
use App\Enums\ProductRelationType;
use App\Enums\ProductStatus;
use App\Enums\SeoRobots;
use App\Models\Concerns\HasLocaleSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasLocaleSlug;
    use HasTranslations;

    protected $fillable = [
        'slug',
        'slug_hy',
        'slug_ru',
        'slug_en',
        'status',
        'name',
        'short_description',
        'description',
        'alt',
        'image',
        'brand',
        'manufacturer',
        'country_of_origin',
        'sku',
        'gtin',
        'volume_value',
        'volume_unit',
        'price_amount',
        'old_price_amount',
        'stock_quantity',
        'availability_status',
        'sort_order',
        'admin_note',
        'stains',
        'surfaces',
        'forbidden_surfaces',
        'ph_value',
        'composition_type',
        'dilution_table',
        'usage_steps',
        'requires_extractor',
        'consumption_note',
        'contact_time',
        'rinse_method',
        'safety_notes',
        'storage_conditions',
        'shelf_life',
        'batch_tracked',
        'sds_url',
        'certificate_note',
        'seo_title',
        'seo_description',
        'og_title',
        'og_description',
        'og_image',
        'canonical_override',
        'seo_robots',
        'primary_keyword',
        'secondary_keywords',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'name' => 'array',
        'short_description' => 'array',
        'description' => 'array',
        'alt' => 'array',
        'price_amount' => 'integer',
        'old_price_amount' => 'integer',
        'stock_quantity' => 'integer',
        'availability_status' => AvailabilityStatus::class,
        'sort_order' => 'integer',
        'stains' => 'array',
        'surfaces' => 'array',
        'forbidden_surfaces' => 'array',
        'ph_value' => 'float',
        'composition_type' => CompositionType::class,
        'dilution_table' => 'array',
        'usage_steps' => 'array',
        'requires_extractor' => 'boolean',
        'consumption_note' => 'array',
        'contact_time' => 'array',
        'rinse_method' => 'array',
        'safety_notes' => 'array',
        'storage_conditions' => 'array',
        'shelf_life' => 'array',
        'batch_tracked' => 'boolean',
        'certificate_note' => 'array',
        'seo_title' => 'array',
        'seo_description' => 'array',
        'og_title' => 'array',
        'og_description' => 'array',
        'seo_robots' => SeoRobots::class,
        'secondary_keywords' => 'array',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_product')
            ->withPivot('is_primary');
    }

    public function primaryCategory(): ?ProductCategory
    {
        return $this->categories->firstWhere('pivot.is_primary', true)
            ?? $this->categories->first();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_primary', true)
            ?? $this->images->first();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'product_service');
    }

    public function related(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_related', 'product_id', 'related_product_id')
            ->wherePivot('type', ProductRelationType::Related->value);
    }

    public function accessories(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_related', 'product_id', 'related_product_id')
            ->wherePivot('type', ProductRelationType::Accessory->value);
    }

    public function taskPages(): BelongsToMany
    {
        return $this->belongsToMany(MarketTaskPage::class, 'market_task_page_product');
    }
}

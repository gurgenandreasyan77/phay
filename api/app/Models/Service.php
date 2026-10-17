<?php

namespace App\Models;

use App\Enums\PriceType;
use App\Enums\SeoRobots;
use App\Enums\ServiceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use BelongsToTenant, HasTranslations;

    protected $fillable = [
        'category_id',
        'slug',
        'icon',
        'image',
        'sort_order',
        'status',
        'name',
        'short_description',
        'description',
        'seo_title',
        'seo_description',
        'who_its_for',
        'included_items',
        'excluded_items',
        'process_steps',
        'equipment',
        'duration_note',
        'price_factors',
        'faq',
        'car_types',
        'materials',
        'contamination_types',
        'price_type',
        'price_amount',
        'price_min',
        'price_max',
        'price_by_car_type',
        'duration_minutes',
        'drying_time_note',
        'warnings',
        'og_title',
        'og_description',
        'og_image',
        'canonical_override',
        'seo_robots',
    ];

    protected $casts = [
        'name' => 'array',
        'short_description' => 'array',
        'description' => 'array',
        'seo_title' => 'array',
        'seo_description' => 'array',
        'who_its_for' => 'array',
        'included_items' => 'array',
        'excluded_items' => 'array',
        'process_steps' => 'array',
        'equipment' => 'array',
        'duration_note' => 'array',
        'price_factors' => 'array',
        'faq' => 'array',
        'sort_order' => 'integer',
        'status' => ServiceStatus::class,
        'car_types' => 'array',
        'materials' => 'array',
        'contamination_types' => 'array',
        'price_type' => PriceType::class,
        'price_amount' => 'integer',
        'price_min' => 'integer',
        'price_max' => 'integer',
        'price_by_car_type' => 'array',
        'duration_minutes' => 'integer',
        'drying_time_note' => 'array',
        'warnings' => 'array',
        'og_title' => 'array',
        'og_description' => 'array',
        'seo_robots' => SeoRobots::class,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_service');
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(ServiceGalleryItem::class)->orderBy('sort_order');
    }

    public function slugRedirects(): HasMany
    {
        return $this->hasMany(ServiceSlugRedirect::class);
    }
}

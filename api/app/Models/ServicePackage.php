<?php

namespace App\Models;

use App\Enums\PriceType;
use App\Enums\ServiceStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackage extends Model
{
    use HasTranslations;

    protected $fillable = [
        'category_id',
        'slug',
        'status',
        'name',
        'short_description',
        'description',
        'included_items',
        'price_type',
        'price_amount',
        'price_min',
        'price_max',
        'price_by_car_type',
        'duration_minutes',
        'image',
        'sort_order',
    ];

    protected $casts = [
        'status' => ServiceStatus::class,
        'name' => 'array',
        'short_description' => 'array',
        'description' => 'array',
        'included_items' => 'array',
        'price_type' => PriceType::class,
        'price_amount' => 'integer',
        'price_min' => 'integer',
        'price_max' => 'integer',
        'price_by_car_type' => 'array',
        'duration_minutes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }
}

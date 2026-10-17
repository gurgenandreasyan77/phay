<?php

namespace App\Models;

use App\Enums\AdditionalServiceCategory;
use App\Enums\PriceUnit;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class AdditionalService extends Model
{
    use BelongsToTenant, HasTranslations;

    protected $fillable = [
        'slug',
        'category',
        'name',
        'note',
        'alt',
        'image',
        'price_amount',
        'price_unit',
        'discount_percent',
        'service_slug',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'category' => AdditionalServiceCategory::class,
        'name' => 'array',
        'note' => 'array',
        'alt' => 'array',
        'price_amount' => 'integer',
        'price_unit' => PriceUnit::class,
        'discount_percent' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}

<?php

namespace App\Models;

use App\Enums\CarBodyType;
use App\Enums\ContactChannelType;
use App\Enums\InteriorMaterial;
use App\Enums\LeadFulfillmentType;
use App\Enums\LeadStatus;
use App\Enums\Locale;
use App\Enums\PropertyType;
use App\Enums\SoilingLevel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'service_id',
        'product_id',
        'fulfillment_type',
        'package_id',
        'property_type',
        'area_sqm',
        'address',
        'preferred_date',
        'comment',
        'preferred_locale',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'status',
        'consent',
        'car_brand',
        'car_model',
        'car_year',
        'car_body_type',
        'seats_count',
        'interior_material',
        'soiling_level',
        'contamination_types',
        'has_odor',
        'odor_source',
        'has_pet_hair',
        'has_child_seat',
        'can_bring_car',
        'preferred_channel',
        'photos',
        'admin_comment',
        'estimated_price_min',
        'estimated_price_max',
    ];

    protected $casts = [
        'property_type' => PropertyType::class,
        'fulfillment_type' => LeadFulfillmentType::class,
        'preferred_locale' => Locale::class,
        'status' => LeadStatus::class,
        'preferred_date' => 'date',
        'area_sqm' => 'integer',
        'consent' => 'boolean',
        'car_year' => 'integer',
        'car_body_type' => CarBodyType::class,
        'seats_count' => 'integer',
        'interior_material' => InteriorMaterial::class,
        'soiling_level' => SoilingLevel::class,
        'contamination_types' => 'array',
        'has_odor' => 'boolean',
        'has_pet_hair' => 'boolean',
        'has_child_seat' => 'boolean',
        'can_bring_car' => 'boolean',
        'preferred_channel' => ContactChannelType::class,
        'photos' => 'array',
        'estimated_price_min' => 'integer',
        'estimated_price_max' => 'integer',
    ];

    protected $attributes = [
        'status' => LeadStatus::New,
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'package_id');
    }
}

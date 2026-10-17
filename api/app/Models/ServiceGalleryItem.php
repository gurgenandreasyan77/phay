<?php

namespace App\Models;

use App\Enums\CarBodyType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceGalleryItem extends Model
{
    use HasTranslations;

    protected $fillable = [
        'service_id',
        'car_type',
        'label',
        'description',
        'before_image',
        'after_image',
        'alt_before',
        'alt_after',
        'performed_at',
        'client_consent',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'car_type' => CarBodyType::class,
        'label' => 'array',
        'description' => 'array',
        'alt_before' => 'array',
        'alt_after' => 'array',
        'performed_at' => 'date',
        'client_consent' => 'boolean',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopePubliclyVisible($query)
    {
        return $query->where('client_consent', true)->where('is_published', true);
    }
}

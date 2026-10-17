<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasLocaleSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MarketTaskPage extends Model
{
    use BelongsToTenant, HasLocaleSlug;
    use HasTranslations;

    protected $fillable = [
        'slug_hy',
        'slug_ru',
        'slug_en',
        'status',
        'title',
        'intro',
        'selection_rules',
        'precautions',
        'seo_title',
        'seo_description',
        'related_service_id',
        'sort_order',
    ];

    protected $casts = [
        'status' => PageStatus::class,
        'title' => 'array',
        'intro' => 'array',
        'selection_rules' => 'array',
        'precautions' => 'array',
        'seo_title' => 'array',
        'seo_description' => 'array',
        'sort_order' => 'integer',
    ];

    public function relatedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'related_service_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'market_task_page_product');
    }
}

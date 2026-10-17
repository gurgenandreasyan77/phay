<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasLocaleSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductCategory extends Model
{
    use BelongsToTenant, HasLocaleSlug;
    use HasTranslations;

    protected $fillable = [
        'slug_hy',
        'slug_ru',
        'slug_en',
        'name',
        'short_description',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'short_description' => 'array',
        'sort_order' => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category_product')
            ->withPivot('is_primary');
    }
}

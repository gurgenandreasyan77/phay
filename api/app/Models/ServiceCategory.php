<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use HasTranslations;

    protected $fillable = [
        'slug',
        'icon',
        'sort_order',
        'name',
        'description',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'sort_order' => 'integer',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}

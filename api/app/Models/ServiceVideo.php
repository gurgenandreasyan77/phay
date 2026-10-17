<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceVideo extends Model
{
    use HasTranslations;

    protected $fillable = [
        'service_id',
        'title',
        'video_path',
        'poster_image',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'title' => 'array',
        'sort_order' => 'integer',
        'is_published' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}

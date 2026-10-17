<?php

namespace App\Models;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use HasTranslations;

    protected $fillable = [
        'slug',
        'category',
        'tags',
        'status',
        'published_at',
        'author_name',
        'featured_image_path',
        'featured_image_alt',
        'title',
        'excerpt',
        'content',
        'seo_title',
        'seo_description',
        'related_post_slugs',
        'faq',
    ];

    protected $casts = [
        'category' => PostCategory::class,
        'status' => PostStatus::class,
        'published_at' => 'datetime',
        'tags' => 'array',
        'featured_image_alt' => 'array',
        'title' => 'array',
        'excerpt' => 'array',
        'content' => 'array',
        'seo_title' => 'array',
        'seo_description' => 'array',
        'related_post_slugs' => 'array',
        'faq' => 'array',
    ];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'slug', $value)
            ->where('status', PostStatus::Published)
            ->where('published_at', '<=', now())
            ->firstOrFail();
    }
}

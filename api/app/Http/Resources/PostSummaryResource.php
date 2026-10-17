<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'category' => $this->category->value,
            'tags' => $this->tags ?? [],
            'authorName' => $this->author_name,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'featuredImage' => $this->featured_image_path ? [
                'path' => $this->featured_image_path,
                'alt' => $this->featured_image_alt ? $this->trans('featured_image_alt') : null,
            ] : null,
            'title' => $this->trans('title'),
            'excerpt' => $this->excerpt ? $this->trans('excerpt') : null,
        ];
    }
}

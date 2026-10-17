<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            'content' => $this->trans('content'),
            'seoTitle' => $this->seo_title ? $this->trans('seo_title') : null,
            'seoDescription' => $this->seo_description ? $this->trans('seo_description') : null,
            'faq' => $this->faq ? $this->transList('faq') : null,
            'relatedServices' => ServiceResource::collection($this->whenLoaded('services')),
            'relatedPosts' => PostSummaryResource::collection($this->relatedPosts()),
        ];
    }

    private function relatedPosts()
    {
        $slugs = $this->related_post_slugs ?? [];

        if (empty($slugs)) {
            return collect();
        }

        return Post::query()
            ->whereIn('slug', $slugs)
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->get();
    }
}

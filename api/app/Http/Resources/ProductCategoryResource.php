<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->localizedSlug(),
            // Every locale's slug, so the frontend can build correct
            // per-locale hreflang alternates without an extra request —
            // categories (unlike Service/Post) have an independent slug per
            // locale, so the current-locale slug alone isn't enough.
            'slugs' => ['hy' => $this->slug_hy, 'ru' => $this->slug_ru, 'en' => $this->slug_en],
            'icon' => $this->icon,
            'name' => $this->trans('name'),
            'shortDescription' => $this->short_description ? $this->trans('short_description') : null,
            'sortOrder' => $this->sort_order,
        ];
    }
}

<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slugHy' => $this->slug_hy,
            'slugRu' => $this->slug_ru,
            'slugEn' => $this->slug_en,
            'icon' => $this->icon,
            'sortOrder' => $this->sort_order,
            'name' => $this->name ?? (object) [],
            'shortDescription' => $this->short_description ?? (object) [],
            'productCount' => $this->whenCounted('products'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

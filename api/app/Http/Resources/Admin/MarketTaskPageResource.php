<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketTaskPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slugHy' => $this->slug_hy,
            'slugRu' => $this->slug_ru,
            'slugEn' => $this->slug_en,
            'status' => $this->status->value,
            'sortOrder' => $this->sort_order,
            'title' => $this->title ?? (object) [],
            'intro' => $this->intro ?? (object) [],
            'selectionRules' => $this->selection_rules ?? (object) [],
            'precautions' => $this->precautions ?? (object) [],
            'seoTitle' => $this->seo_title ?? (object) [],
            'seoDescription' => $this->seo_description ?? (object) [],
            'relatedServiceId' => $this->related_service_id,
            'productIds' => $this->products->pluck('id')->values(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketTaskPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->localizedSlug(),
            'slugs' => ['hy' => $this->slug_hy, 'ru' => $this->slug_ru, 'en' => $this->slug_en],
            'title' => $this->trans('title'),
            'intro' => $this->intro ? $this->trans('intro') : null,
            'selectionRules' => $this->selection_rules ? $this->transList('selection_rules') : null,
            'precautions' => $this->precautions ? $this->transList('precautions') : null,
            'seoTitle' => $this->seo_title ? $this->trans('seo_title') : $this->trans('title'),
            'seoDescription' => $this->seo_description ? $this->trans('seo_description') : null,
            'relatedService' => new ServiceResource($this->whenLoaded('relatedService')),
            'products' => ProductSummaryResource::collection($this->whenLoaded('products')),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

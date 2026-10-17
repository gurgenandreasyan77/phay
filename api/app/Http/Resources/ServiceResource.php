<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'image' => $this->image,
            'status' => $this->status->value,
            'isOrderable' => $this->status->isOrderable(),
            'name' => $this->trans('name'),
            'shortDescription' => $this->short_description ? $this->trans('short_description') : null,
            'description' => $this->description ? $this->trans('description') : null,
            'seoTitle' => $this->seo_title ? $this->trans('seo_title') : null,
            'seoDescription' => $this->seo_description ? $this->trans('seo_description') : null,
            'ogTitle' => $this->og_title ? $this->trans('og_title') : null,
            'ogDescription' => $this->og_description ? $this->trans('og_description') : null,
            'ogImage' => $this->og_image,
            'canonicalOverride' => $this->canonical_override,
            'seoRobots' => $this->seo_robots?->value,
            'whoItsFor' => $this->who_its_for ? $this->trans('who_its_for') : null,
            'includedItems' => $this->included_items ? $this->transList('included_items') : null,
            'excludedItems' => $this->excluded_items ? $this->transList('excluded_items') : null,
            'processSteps' => $this->process_steps ? $this->transList('process_steps') : null,
            'equipment' => $this->equipment ? $this->trans('equipment') : null,
            'durationNote' => $this->duration_note ? $this->trans('duration_note') : null,
            'priceFactors' => $this->price_factors ? $this->transList('price_factors') : null,
            'faq' => $this->faq ? $this->transList('faq') : null,
            'warnings' => $this->warnings ? $this->transList('warnings') : null,
            'dryingTimeNote' => $this->drying_time_note ? $this->trans('drying_time_note') : null,

            'carTypes' => $this->car_types ?? [],
            'materials' => $this->materials ?? [],
            'contaminationTypes' => $this->contamination_types ?? [],
            'priceType' => $this->price_type?->value,
            'priceAmount' => $this->price_amount,
            'priceMin' => $this->price_min,
            'priceMax' => $this->price_max,
            'priceByCarType' => $this->price_by_car_type ?? (object) [],
            'durationMinutes' => $this->duration_minutes,

            'updatedAt' => $this->updated_at?->toIso8601String(),
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'products' => ProductSummaryResource::collection($this->whenLoaded('products')),
            'galleryItems' => ServiceGalleryItemResource::collection($this->whenLoaded('galleryItems')),
        ];
    }
}

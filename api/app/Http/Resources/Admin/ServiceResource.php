<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categoryId' => $this->category_id,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'image' => $this->image,
            'sortOrder' => $this->sort_order,
            'status' => $this->status->value,

            'name' => $this->name ?? (object) [],
            'shortDescription' => $this->short_description ?? (object) [],
            'description' => $this->description ?? (object) [],
            'whoItsFor' => $this->who_its_for ?? (object) [],
            'includedItems' => $this->included_items ?? (object) [],
            'excludedItems' => $this->excluded_items ?? (object) [],
            'processSteps' => $this->process_steps ?? (object) [],
            'equipment' => $this->equipment ?? (object) [],
            'durationNote' => $this->duration_note ?? (object) [],
            'priceFactors' => $this->price_factors ?? (object) [],
            'faq' => $this->faq ?? (object) [],
            'warnings' => $this->warnings ?? (object) [],
            'dryingTimeNote' => $this->drying_time_note ?? (object) [],

            'carTypes' => $this->car_types ?? [],
            'materials' => $this->materials ?? [],
            'contaminationTypes' => $this->contamination_types ?? [],
            'priceType' => $this->price_type?->value,
            'priceAmount' => $this->price_amount,
            'priceMin' => $this->price_min,
            'priceMax' => $this->price_max,
            'priceByCarType' => $this->price_by_car_type ?? (object) [],
            'durationMinutes' => $this->duration_minutes,

            'seoTitle' => $this->seo_title ?? (object) [],
            'seoDescription' => $this->seo_description ?? (object) [],
            'ogTitle' => $this->og_title ?? (object) [],
            'ogDescription' => $this->og_description ?? (object) [],
            'ogImage' => $this->og_image,
            'canonicalOverride' => $this->canonical_override,
            'seoRobots' => $this->seo_robots?->value,

            'productIds' => $this->whenLoaded('products', fn () => $this->products->pluck('id')->values()),

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

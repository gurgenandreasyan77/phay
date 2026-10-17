<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'name' => $this->trans('name'),
            'shortDescription' => $this->short_description ? $this->trans('short_description') : null,
            'description' => $this->description ? $this->trans('description') : null,
            'includedItems' => $this->included_items ? $this->transList('included_items') : null,
            'priceType' => $this->price_type?->value,
            'priceAmount' => $this->price_amount,
            'priceMin' => $this->price_min,
            'priceMax' => $this->price_max,
            'priceByCarType' => $this->price_by_car_type ?? (object) [],
            'durationMinutes' => $this->duration_minutes,
            'image' => $this->image,
        ];
    }
}

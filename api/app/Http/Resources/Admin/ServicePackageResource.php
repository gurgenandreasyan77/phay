<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categoryId' => $this->category_id,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'name' => $this->name ?? (object) [],
            'shortDescription' => $this->short_description ?? (object) [],
            'description' => $this->description ?? (object) [],
            'includedItems' => $this->included_items ?? (object) [],
            'priceType' => $this->price_type?->value,
            'priceAmount' => $this->price_amount,
            'priceMin' => $this->price_min,
            'priceMax' => $this->price_max,
            'priceByCarType' => $this->price_by_car_type ?? (object) [],
            'durationMinutes' => $this->duration_minutes,
            'image' => $this->image,
            'sortOrder' => $this->sort_order,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

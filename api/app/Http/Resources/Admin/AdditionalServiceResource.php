<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdditionalServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'category' => $this->category->value,
            'name' => $this->name ?? (object) [],
            'note' => $this->note ?? (object) [],
            'alt' => $this->alt ?? (object) [],
            'image' => $this->image,
            'priceAmount' => $this->price_amount,
            'priceUnit' => $this->price_unit->value,
            'discountPercent' => $this->discount_percent,
            'serviceSlug' => $this->service_slug,
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

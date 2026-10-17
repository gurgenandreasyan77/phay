<?php

namespace App\Http\Resources;

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
            'name' => $this->trans('name'),
            'note' => $this->note ? $this->trans('note') : null,
            'alt' => $this->trans('alt'),
            'image' => $this->image,
            'priceAmount' => $this->price_amount,
            'priceUnit' => $this->price_unit->value,
            'discountPercent' => $this->discount_percent,
            'serviceSlug' => $this->service_slug,
        ];
    }
}

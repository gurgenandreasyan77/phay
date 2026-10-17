<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceGalleryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serviceSlug' => $this->whenLoaded('service', fn () => $this->service?->slug),
            'carType' => $this->car_type?->value,
            'label' => $this->label ? $this->trans('label') : null,
            'description' => $this->description ? $this->trans('description') : null,
            'beforeImage' => $this->before_image,
            'afterImage' => $this->after_image,
            'altBefore' => $this->alt_before ? $this->trans('alt_before') : null,
            'altAfter' => $this->alt_after ? $this->trans('alt_after') : null,
            'performedAt' => $this->performed_at?->toDateString(),
        ];
    }
}

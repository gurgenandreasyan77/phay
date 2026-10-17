<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceGalleryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serviceId' => $this->service_id,
            'carType' => $this->car_type?->value,
            'label' => $this->label ?? (object) [],
            'description' => $this->description ?? (object) [],
            'beforeImage' => $this->before_image,
            'afterImage' => $this->after_image,
            'altBefore' => $this->alt_before ?? (object) [],
            'altAfter' => $this->alt_after ?? (object) [],
            'performedAt' => $this->performed_at?->toDateString(),
            'clientConsent' => $this->client_consent,
            'isPublished' => $this->is_published,
            'sortOrder' => $this->sort_order,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

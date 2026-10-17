<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'name' => $this->trans('name'),
            'description' => $this->description ? $this->trans('description') : null,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
        ];
    }
}

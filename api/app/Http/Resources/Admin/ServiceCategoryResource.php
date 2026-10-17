<?php

namespace App\Http\Resources\Admin;

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
            'sortOrder' => $this->sort_order,
            'name' => $this->name ?? (object) [],
            'description' => $this->description ?? (object) [],
            'servicesCount' => $this->whenCounted('services'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

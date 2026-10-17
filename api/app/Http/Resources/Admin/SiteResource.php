<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'publicKey' => $this->public_key,
            'allowedOrigins' => $this->allowed_origins ?? [],
            'isActive' => $this->is_active,
            'tenant' => $this->whenLoaded('tenant', fn () => ['id' => $this->tenant->id, 'name' => $this->tenant->name]),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

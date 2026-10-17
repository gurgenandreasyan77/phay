<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'defaultLocale' => $this->default_locale,
            'status' => $this->status,
            // The signed-in user's role here, or null for a super admin who
            // isn't a member.
            'role' => $this->whenPivotLoaded('tenant_user', fn () => $this->pivot->role),
            'sitesCount' => $this->whenCounted('sites'),
            'usersCount' => $this->whenCounted('users'),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

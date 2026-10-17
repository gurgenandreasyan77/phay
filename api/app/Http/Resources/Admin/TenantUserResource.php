<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A person who can sign in to a business's admin panel. */
class TenantUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->whenPivotLoaded('tenant_user', fn () => $this->pivot->role),
            'isSuperAdmin' => $this->is_super_admin,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

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
            // The login: what the person types on the sign-in page.
            'email' => $this->email,
            'role' => $this->whenPivotLoaded('tenant_user', fn () => $this->pivot->role),
            'isSuperAdmin' => $this->is_super_admin,
            'platformRole' => $this->platformRole()?->value,
            // Account security, for staff to see who is set up.
            'activated' => $this->isActivated(),
            'twoFactorEnabled' => $this->hasTwoFactorEnabled(),
            'emailVerified' => $this->email_verified_at !== null,
            'phoneVerified' => $this->phone_verified_at !== null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

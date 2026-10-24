<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A member of Servio's platform team (super admin or moderator). */
class PlatformUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->platformRole()?->value,
            // Account security, for staff to see who is set up.
            'activated' => $this->isActivated(),
            'twoFactorEnabled' => $this->hasTwoFactorEnabled(),
            'emailVerified' => $this->email_verified_at !== null,
            'phoneVerified' => $this->phone_verified_at !== null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

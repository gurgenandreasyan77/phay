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
            'logo' => $this->logo,
            'slug' => $this->slug,
            'defaultLocale' => $this->default_locale,
            'status' => $this->status,
            // The signed-in user's role here, or null for a super admin who
            // isn't a member.
            'role' => $this->whenPivotLoaded('tenant_user', fn () => $this->pivot->role),
            'sitesCount' => $this->whenCounted('sites'),
            'usersCount' => $this->whenCounted('users'),
            // What the business filled in on its settings page (platform list).
            'address' => $this->whenLoaded('siteSetting', fn () => $this->siteSetting?->address ?? (object) []),
            'contacts' => $this->whenLoaded('contactChannels', fn () => $this->contactChannels
                ->where('is_active', true)
                ->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])
                ->map(fn ($channel) => [
                    'type' => $channel->type->value,
                    'value' => $channel->value,
                    'label' => $channel->label,
                ])
                ->values()),
            // Messages of this business the platform team hasn't read.
            'unreadMessages' => $this->when(isset($this->unread_messages_count), fn () => (int) $this->unread_messages_count),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

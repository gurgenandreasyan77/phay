<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenant = $this->resource['tenant'];
        $setting = $this->resource['setting'];
        $channels = $this->resource['channels'];

        return [
            'business' => [
                'name' => $tenant->name,
                'logo' => $tenant->logo,
            ],
            'address' => $setting->address ?? (object) [],
            'channels' => ContactChannelResource::collection($channels),
        ];
    }
}

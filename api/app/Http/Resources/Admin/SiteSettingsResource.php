<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $setting = $this->resource['setting'];
        $channels = $this->resource['channels'];

        return [
            'address' => $setting->address ?? (object) [],
            'channels' => ContactChannelResource::collection($channels),
        ];
    }
}

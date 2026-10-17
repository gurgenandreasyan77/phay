<?php

namespace App\Http\Resources;

use App\Features\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class SiteSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $setting = $this->resource['setting'];
        $channels = $this->resource['channels'];

        return [
            'address' => $setting->address ? $setting->trans('address') : null,
            'channels' => ContactChannelResource::collection($channels),
            // Only the on/off status is public — no metadata, history or
            // who-changed-it, since this endpoint is unauthenticated.
            'features' => collect(FeatureFlag::cases())->mapWithKeys(
                fn (FeatureFlag $flag) => [Str::camel($flag->value) => Feature::active($flag->value)]
            ),
        ];
    }
}

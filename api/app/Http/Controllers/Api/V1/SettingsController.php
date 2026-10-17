<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteSettingsResource;
use App\Models\ContactChannel;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $channels = ContactChannel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return (new SiteSettingsResource([
            'setting' => SiteSetting::current(),
            'channels' => $channels,
        ]))->response();
    }
}

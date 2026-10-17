<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Http\Resources\Admin\SiteSettingsResource;
use App\Models\ContactChannel;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->respond();
    }

    public function update(UpdateSiteSettingsRequest $request): JsonResponse
    {
        $setting = SiteSetting::current();
        $setting->update($request->validated());

        return $this->respond();
    }

    private function respond(): JsonResponse
    {
        $channels = ContactChannel::query()->orderBy('sort_order')->get();

        return (new SiteSettingsResource([
            'setting' => SiteSetting::current(),
            'channels' => $channels,
        ]))->response();
    }
}

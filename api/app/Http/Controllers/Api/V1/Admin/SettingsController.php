<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Http\Resources\Admin\SiteSettingsResource;
use App\Models\ContactChannel;
use App\Models\SiteSetting;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The business's own settings: its profile (name, logo), address and
 * contact channels. The platform team sees the same data in its list of
 * businesses.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(): JsonResponse
    {
        return $this->respond();
    }

    public function update(UpdateSiteSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $this->context->current()->update(['name' => trim($validated['name'])]);
        }

        if (array_key_exists('address', $validated)) {
            SiteSetting::current()->update(['address' => $validated['address']]);
        }

        return $this->respond();
    }

    /** Replaces the logo with an uploaded image (the old file is removed). */
    public function storeLogo(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $tenant = $this->context->current();
        $file = $request->file('file');
        // Same layout as the other uploads: one folder per business.
        $path = $file->storeAs(
            'uploads/'.$tenant->id.'/logo',
            Str::uuid()->toString().'.'.$file->extension(),
            'public',
        );

        $this->deleteLogoFile($tenant->logo);
        $tenant->update(['logo' => Storage::disk('public')->url($path)]);

        return $this->respond();
    }

    public function destroyLogo(): JsonResponse
    {
        $tenant = $this->context->current();

        $this->deleteLogoFile($tenant->logo);
        $tenant->update(['logo' => null]);

        return $this->respond();
    }

    /** Only a file this business uploaded as its logo, never anything else on the disk. */
    private function deleteLogoFile(?string $url): void
    {
        $prefix = 'uploads/'.$this->context->id().'/logo/';
        $path = $url === null ? null : strstr($url, $prefix);

        if ($path !== false && $path !== null && ! str_contains($path, '..')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function respond(): JsonResponse
    {
        $channels = ContactChannel::query()->orderBy('sort_order')->get();

        return (new SiteSettingsResource([
            'tenant' => $this->context->current()->fresh(),
            'setting' => SiteSetting::current(),
            'channels' => $channels,
        ]))->response();
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SiteResource;
use App\Models\Site;
use App\Models\Tenant;
use App\Sites\SiteCacheTag;
use App\Sites\SiteRevalidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Sites of every business on the platform (super admin only, see routes).
 * Site is a platform model (not BelongsToTenant): the public API looks keys
 * up across all businesses.
 */
class SiteController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SiteResource::collection(Site::query()->with('tenant')->orderBy('tenant_id')->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->validate([
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
        ])['tenant_id'];

        $site = Tenant::query()->findOrFail($tenantId)->sites()->create($this->validated($request, creating: true));

        return (new SiteResource($site->fresh('tenant')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Site $site): SiteResource
    {
        $site->update($this->validated($request, creating: false));

        return new SiteResource($site->fresh('tenant'));
    }

    /**
     * Issues a new key and invalidates the old one at once: the site keeps
     * failing with 401 until its X-Site-Key is updated.
     */
    public function rotateKey(Site $site): SiteResource
    {
        $site->update(['public_key' => Site::generateKey()]);

        return new SiteResource($site->fresh('tenant'));
    }

    /**
     * Sends the site one cache refresh right now, so whoever set up the
     * webhook sees at once whether the address and secret work.
     */
    public function checkRevalidation(Site $site, SiteRevalidator $revalidator): JsonResponse
    {
        if (! $site->canRevalidate()) {
            return response()->json(['message' => 'Կայքի webhook-ի հասցեն կամ գաղտնաբառը նշված չէ։'], 422);
        }

        return response()->json(['data' => $revalidator->send($site, [SiteCacheTag::SiteSettings->value])]);
    }

    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        $validated = $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/i'],
            'allowed_origins' => ['sometimes', 'nullable', 'array', 'max:20'],
            // scheme://host[:port], exactly what a browser sends as Origin.
            'allowed_origins.*' => ['string', 'max:255', 'regex:#^https?://[a-z0-9.-]+(:\d+)?$#i'],
            'revalidate_url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            // Empty or missing keeps the current one; it is never sent back.
            'revalidate_secret' => ['sometimes', 'nullable', 'string', 'min:16', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('revalidate_secret', $validated) && blank($validated['revalidate_secret'])) {
            unset($validated['revalidate_secret']);
        }

        if (array_key_exists('allowed_origins', $validated)) {
            $validated['allowed_origins'] = array_values(array_unique($validated['allowed_origins'] ?? [])) ?: null;
        }

        return $validated;
    }
}

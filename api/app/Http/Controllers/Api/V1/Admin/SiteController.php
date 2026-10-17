<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SiteResource;
use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sites connected to the current business. Site is a platform model (not
 * BelongsToTenant, the public API looks keys up across all businesses), so
 * every action here checks the site belongs to the current business itself.
 */
class SiteController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(): AnonymousResourceCollection
    {
        return SiteResource::collection($this->context->current()->sites()->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $site = $this->context->current()->sites()->create($this->validated($request, creating: true));

        return (new SiteResource($site->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request, Site $site): SiteResource
    {
        $this->authorizeSite($site);

        $site->update($this->validated($request, creating: false));

        return new SiteResource($site->fresh());
    }

    /**
     * Issues a new key and invalidates the old one at once: the site keeps
     * failing with 401 until its X-Site-Key is updated.
     */
    public function rotateKey(Site $site): SiteResource
    {
        $this->authorizeSite($site);

        $site->update(['public_key' => Site::generateKey()]);

        return new SiteResource($site->fresh());
    }

    private function authorizeSite(Site $site): void
    {
        abort_unless($site->tenant_id === $this->context->id(), 404);
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
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('allowed_origins', $validated)) {
            $validated['allowed_origins'] = array_values(array_unique($validated['allowed_origins'] ?? [])) ?: null;
        }

        return $validated;
    }
}

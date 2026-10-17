<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Optional modules (feature flags) of any business, from the platform level
 * (super admin only): the same as the current business's feature flags, run
 * as the given business so Pennant and the change log scope to it.
 */
class TenantFeatureController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureFlagController $flags,
    ) {}

    public function index(Tenant $tenant): JsonResponse
    {
        return $this->context->run($tenant, fn () => $this->flags->index());
    }

    public function update(Request $request, Tenant $tenant, string $key): JsonResponse
    {
        return $this->context->run($tenant, fn () => $this->flags->update($request, $key));
    }
}

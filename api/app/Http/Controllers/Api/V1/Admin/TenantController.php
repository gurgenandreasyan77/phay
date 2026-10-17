<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Businesses on the platform (super admin only, see routes): the first
 * clients are onboarded by hand (see stage-1 plan).
 */
class TenantController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TenantResource::collection(Tenant::query()->withCount(['sites', 'users'])->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-tenants');

        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('tenants', 'slug')],
            'default_locale' => ['nullable', Rule::in(['hy', 'ru', 'en'])],
        ]);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'default_locale' => $validated['default_locale'] ?? 'hy',
            'locales' => ['hy', 'ru', 'en'],
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        return (new TenantResource($tenant->loadCount(['sites', 'users'])))->response()->setStatusCode(201);
    }
}

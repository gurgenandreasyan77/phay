<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TenantResource;
use App\Models\Tenant;
use App\Subscriptions\Subscriptions;
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
    /**
     * ?search= finds a business by its name or slug, any of its contacts
     * (a phone matches with or without spaces, dashes or +) or its address.
     * Matched in PHP: Armenian text compares case-insensitively the same way
     * on every database, and the platform has tens of businesses, not
     * thousands (then: a search column).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));

        $tenants = Tenant::query()
            ->withCount([
                'sites',
                'users',
                'supportMessages as unread_messages_count' => fn ($query) => $query->unreadFrom(false),
            ])
            ->with(['siteSetting', 'contactChannels'])
            ->orderBy('id')
            ->get();

        if ($search !== '') {
            $tenants = $tenants->filter(fn (Tenant $tenant) => self::matches($tenant, $search))->values();
        }

        return TenantResource::collection($tenants);
    }

    /** Whether the business matches a lowercased ?search= (also used by the header search). */
    public static function matches(Tenant $tenant, string $search): bool
    {
        $digits = preg_replace('/\D+/', '', $search);

        $texts = [
            $tenant->name,
            $tenant->slug,
            ...array_values(array_filter((array) ($tenant->siteSetting?->address ?? []), 'is_string')),
            ...$tenant->contactChannels->pluck('label')->filter()->all(),
        ];

        foreach ($tenant->contactChannels as $channel) {
            $texts[] = $channel->value;

            // "55 33-76 33", "+374 55337633" and "553376" find the same phone.
            if (strlen($digits) >= 3 && str_contains(preg_replace('/\D+/', '', $channel->value), $digits)) {
                return true;
            }
        }

        foreach ($texts as $text) {
            if (str_contains(mb_strtolower((string) $text), $search)) {
                return true;
            }
        }

        return false;
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

        // Its trial on the default plan starts today.
        app(Subscriptions::class)->for($tenant);

        return (new TenantResource($tenant->loadCount(['sites', 'users'])))->response()->setStatusCode(201);
    }
}

<?php

namespace App\Http\Middleware;

use App\Subscriptions\Subscriptions;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin API (after auth:sanctum): the signed-in user's current business.
 * Runs after authentication, so it always has a user.
 */
class ResolveAdminTenant
{
    /**
     * What a business whose subscription ended can still open: who it is,
     * its subscription and the chat with the platform (to pay and come back).
     */
    private const OPEN_WITHOUT_SUBSCRIPTION = [
        'api/v1/admin/me',
        'api/v1/admin/logout',
        'api/v1/admin/switch-tenant',
        'api/v1/admin/password',
        'api/v1/admin/security',
        'api/v1/admin/security/*',
        'api/v1/admin/subscription',
        'api/v1/admin/subscription/*',
        'api/v1/admin/support/*',
        'api/v1/admin/notifications',
        'api/v1/admin/notifications/*',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly Subscriptions $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $user->resolveCurrentTenant();

        abort_unless($tenant, 403, 'Your account is not linked to any business.');

        // A suspended business can't be worked on by its own people; the
        // platform staff still can (e.g. to look into it).
        abort_unless(
            $tenant->isActive() || $user->isPlatformStaff(),
            403,
            'This business is suspended.',
        );

        return $this->context->run($tenant, function () use ($request, $next, $tenant, $user) {
            // The business's own people: warned before the end, locked out
            // (data kept) after it. Platform staff always get in.
            if (! $user->isPlatformStaff()) {
                $subscription = $this->subscriptions->sync($tenant);

                if (! $subscription->allowsAccess() && ! $request->is(...self::OPEN_WITHOUT_SUBSCRIPTION)) {
                    return response()->json([
                        'message' => 'The subscription of this business has ended.',
                        'code' => 'subscription_expired',
                    ], 402);
                }
            }

            return $next($request);
        });
    }
}

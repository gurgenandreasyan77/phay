<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * The tenant the current request, command or job acts on.
 *
 * HTTP requests set it in ResolveSiteTenant / ResolveAdminTenant. Anything
 * that never sets one (console commands, seeders, queued jobs, tests that
 * create rows directly) falls back to the default tenant, so code outside a
 * request still reads and writes exactly one tenant's data, never all.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function current(): Tenant
    {
        return $this->tenant ??= Tenant::default();
    }

    public function id(): int
    {
        return $this->current()->getKey();
    }

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Run a callback as another tenant, restoring the previous one after.
     *
     * @template T
     *
     * @param  Closure(Tenant): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}

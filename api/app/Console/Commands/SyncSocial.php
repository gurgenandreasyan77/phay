<?php

namespace App\Console\Commands;

use App\Features\FeatureFlag;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Social\MetaAccounts;
use App\Social\MetaException;
use App\Social\MetaRateLimited;
use App\Social\SocialSync;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Laravel\Pennant\Feature;

/**
 * Reads the connected Instagram and Facebook accounts of every business
 * with the social module on (scheduled every few hours): their figures and
 * posts, and the watched accounts due. A business whose access ended is
 * skipped (its people were told to connect again); one that hit Meta's
 * limit tries again next time.
 */
class SyncSocial extends Command
{
    protected $signature = 'platform:social:sync {--tenant= : Only this business (slug)} {--force : Also those read less than the interval ago}';

    protected $description = 'Refresh the businesses\' Instagram and Facebook figures from Meta';

    /** A business is read again after this many hours. */
    private const INTERVAL_HOURS = 5;

    public function handle(TenantContext $context, SocialSync $sync, MetaAccounts $accounts): int
    {
        if (! $accounts->isConfigured()) {
            $this->warn('META_APP_ID / META_APP_SECRET / META_REDIRECT_URI are not set: nothing to do.');

            return self::SUCCESS;
        }

        $connections = SocialConnection::withoutTenancy()
            ->whereNull('revoked_at')
            ->when(! $this->option('force'), fn ($q) => $q->where(fn ($q) => $q->whereNull('synced_at')->orWhere('synced_at', '<', now()->subHours(self::INTERVAL_HOURS))))
            ->get();

        foreach ($connections as $connection) {
            $tenant = Tenant::query()->find($connection->tenant_id);
            if (! $tenant || ($this->option('tenant') && $tenant->slug !== $this->option('tenant'))) {
                continue;
            }

            if (! Feature::for($tenant)->active(FeatureFlag::Social->value)) {
                $this->line("{$tenant->slug}: module off, skipped");

                continue;
            }

            $context->run($tenant, function () use ($sync, $accounts, $connection, $tenant) {
                try {
                    $result = $sync->sync($connection);
                    $this->line(sprintf('%s: %d accounts, %d posts, %d competitors read', $tenant->slug, $result['accounts'], $result['posts'], $result['competitors']['read']));
                } catch (MetaRateLimited) {
                    $this->warn("{$tenant->slug}: Meta rate limit, next time");
                } catch (MetaException $e) {
                    $this->error("{$tenant->slug}: {$e->getMessage()}");
                }

                $accounts->warnIfExpiring($connection);
            });
        }

        return self::SUCCESS;
    }
}

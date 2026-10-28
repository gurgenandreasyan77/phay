<?php

namespace App\Console\Commands;

use App\Agents\AgentTerms;
use App\Models\Tenant;
use App\Subscriptions\Subscriptions;
use Illuminate\Console\Command;

/**
 * Warns businesses whose subscription (or an AI agent's term) ends soon and
 * tells those whose has ended (scheduled daily). The admin does the same when a business opens
 * it, so a server without the scheduler still sends them, only later.
 */
class SyncSubscriptions extends Command
{
    protected $signature = 'platform:subscriptions:sync';

    protected $description = 'Send subscription warnings and end notices to businesses';

    public function handle(Subscriptions $subscriptions, AgentTerms $agents): int
    {
        Tenant::query()->with('subscription.plan')->each(function (Tenant $tenant) use ($subscriptions, $agents) {
            $subscription = $subscriptions->sync($tenant);
            // Each AI agent's own term too.
            $agents->sync($tenant);
            $this->line(sprintf('%s: %s', $tenant->slug, $subscription->status()->label()));
        });

        return self::SUCCESS;
    }
}

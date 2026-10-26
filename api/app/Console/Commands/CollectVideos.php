<?php

namespace App\Console\Commands;

use App\Media\VideoStudio;
use App\Models\Tenant;
use App\Models\VideoJob;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Videos the AI agents are cutting, in every business (scheduled every
 * minute): a finished one is kept as a file of its answer even when nobody
 * has the chat open, one the media service lost is marked failed.
 */
class CollectVideos extends Command
{
    protected $signature = 'platform:videos:collect';

    protected $description = 'Collect the AI agents\' finished videos from the media service';

    public function handle(TenantContext $context, VideoStudio $studio): int
    {
        $jobs = VideoJob::withoutTenancy()->where('status', VideoJob::RENDERING)->orderBy('id')->get();

        foreach ($jobs->groupBy('tenant_id') as $tenantId => $videos) {
            $tenant = Tenant::query()->find($tenantId);
            if (! $tenant) {
                continue;
            }

            $context->run($tenant, function () use ($studio, $videos) {
                foreach ($videos as $video) {
                    try {
                        $this->line("video #{$video->id}: ".$studio->refresh($video)->status);
                    } catch (Throwable $e) {
                        $this->error("video #{$video->id}: {$e->getMessage()}");
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\DataTables\Duplicates\RowDuplicates;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Marks the copies of rows written in several tables (RowDuplicates), for
 * every business: once after the migration that brought it; later the
 * flags follow every change by themselves.
 */
class RefreshDuplicates extends Command
{
    protected $signature = 'tables:duplicates {--tenant= : Only this business (slug)}';

    protected $description = 'Find rows written in several tables, so each record counts once';

    public function handle(TenantContext $context, RowDuplicates $duplicates): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('id')
            ->get();

        foreach ($tenants as $tenant) {
            $context->run($tenant, function () use ($duplicates, $tenant) {
                $copies = $duplicates->refresh();
                $groups = count($duplicates->review(null, null));
                $this->line("{$tenant->slug}: {$copies} copies counted once, {$groups} groups to look at");
            });
        }

        return self::SUCCESS;
    }
}

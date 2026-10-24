<?php

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;

class ListSites extends Command
{
    protected $signature = 'platform:site:list';

    protected $description = 'List connected sites with their business, X-Site-Key and webhook';

    public function handle(): int
    {
        $this->table(
            ['ID', 'Business', 'Site', 'Domain', 'X-Site-Key', 'Webhook', 'Active'],
            Site::query()->with('tenant')->orderBy('id')->get()->map(fn (Site $site) => [
                $site->id,
                $site->tenant->slug,
                $site->name,
                $site->domain ?? '-',
                $site->public_key,
                $site->revalidate_url ?? '-',
                $site->is_active ? 'yes' : 'no',
            ]),
        );

        return self::SUCCESS;
    }
}

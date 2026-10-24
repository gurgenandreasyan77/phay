<?php

namespace App\Console\Commands;

use App\Http\Middleware\ResolveSiteTenant;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CreateSite extends Command
{
    protected $signature = 'platform:site:create
        {tenant : Business slug or id}
        {name : Site name, e.g. "shop.am"}
        {--domain= : Main domain, e.g. shop.am}
        {--origin=* : Browser origin allowed to call the API with this key (repeatable). None: any origin}';

    protected $description = 'Connect a site to a business and print its X-Site-Key';

    public function handle(): int
    {
        $ref = $this->argument('tenant');
        $tenant = Tenant::query()->where('slug', $ref)
            ->when(ctype_digit((string) $ref), fn ($q) => $q->orWhere('id', (int) $ref))
            ->first();

        if (! $tenant) {
            $this->error("No business with the slug or id [{$ref}].");

            return self::FAILURE;
        }

        $site = Site::create([
            'tenant_id' => $tenant->getKey(),
            'name' => $this->argument('name'),
            'domain' => $this->option('domain'),
            'allowed_origins' => $this->option('origin') ?: null,
            'is_active' => true,
        ]);

        $this->info("Connected site \"{$site->name}\" to \"{$tenant->name}\".");
        $this->line('Site key: '.$site->public_key);
        $this->line('Send it as the '.ResolveSiteTenant::HEADER.' header on every public API request.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenant extends Command
{
    protected $signature = 'platform:tenant:create
        {name : Business name, e.g. "Test Shop"}
        {--slug= : URL-safe id (default: from the name)}
        {--owner= : Email of an existing user to make the owner}
        {--locale=hy : Default locale}';

    protected $description = 'Create a business (tenant) on the platform';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: Str::slug($name);

        if ($slug === '' || Tenant::query()->where('slug', $slug)->exists()) {
            $this->error("A business with the slug [{$slug}] already exists or the slug is empty. Pass --slug.");

            return self::FAILURE;
        }

        $owner = null;

        if ($email = $this->option('owner')) {
            $owner = User::query()->where('email', $email)->first();

            if (! $owner) {
                $this->error("No user with the email [{$email}].");

                return self::FAILURE;
            }
        }

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'default_locale' => $this->option('locale'),
            'locales' => ['hy', 'ru', 'en'],
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $owner?->tenants()->attach($tenant->getKey(), ['role' => 'owner']);

        $this->info("Created business #{$tenant->id} \"{$tenant->name}\" (slug: {$tenant->slug}).");

        if ($owner) {
            $this->line("Owner: {$owner->email}");
        }

        $this->line("Next: php artisan platform:site:create {$tenant->slug} \"My site\" --domain=example.am");

        return self::SUCCESS;
    }
}

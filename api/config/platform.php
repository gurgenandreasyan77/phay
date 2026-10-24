<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default tenant
    |--------------------------------------------------------------------------
    |
    | The business that console commands, seeders and queued jobs act on
    | when nothing else picks one. It is created by the tenancy migration and
    | owns every row that existed before multi-tenancy. The public API never
    | falls back to it: a site must send its X-Site-Key.
    |
    */

    'default_tenant' => env('PLATFORM_DEFAULT_TENANT', 'servio'),

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    |
    | How many days before a subscription ends the business is warned (in
    | its chat with the platform and on a banner in its admin), and the
    | default notice the super admin gives when turning one off.
    |
    */

    'subscription_warn_days' => (int) env('PLATFORM_SUBSCRIPTION_WARN_DAYS', 10),

    /*
    |--------------------------------------------------------------------------
    | Legacy site webhook
    |--------------------------------------------------------------------------
    |
    | Before sites had their own cache-refresh webhook, the admin app sent it
    | to one address from its env. The migration that added the per-site
    | fields copies these into the default tenant's site; nothing else reads
    | them.
    |
    */

    'legacy_site' => [
        'revalidate_url' => env('SITE_REVALIDATE_URL'),
        'revalidate_secret' => env('REVALIDATE_SECRET'),
    ],

];

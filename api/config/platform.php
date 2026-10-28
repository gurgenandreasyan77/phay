<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default tenant
    |--------------------------------------------------------------------------
    |
    | The business that console commands, seeders and queued jobs act on
    | when nothing else picks one, by slug. It is created by the tenancy
    | migration and owns every row that existed before multi-tenancy; when
    | no business has this slug (an older install made under another one),
    | the oldest business is used. The public API never falls back to it:
    | a site must send its X-Site-Key.
    |
    */

    'default_tenant' => env('PLATFORM_DEFAULT_TENANT', 'phay'),

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

    // Each AI agent: a free trial once, and how many days before its end the business is warned.
    'agent_trial_days' => (int) env('PLATFORM_AGENT_TRIAL_DAYS', 7),
    'agent_warn_days' => (int) env('PLATFORM_AGENT_WARN_DAYS', 3),
    // The balance renews a paid AI agent that ended at most this many days ago (App\Billing\BalancePayments).
    'balance_agent_grace_days' => (int) env('PLATFORM_BALANCE_AGENT_GRACE_DAYS', 7),

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

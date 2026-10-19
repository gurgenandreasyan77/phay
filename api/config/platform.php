<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default tenant
    |--------------------------------------------------------------------------
    |
    | The business that requests without an X-Site-Key header, console
    | commands, seeders and queued jobs act on. It is created by the tenancy
    | migration and owns every row that existed before multi-tenancy, so the
    | original site keeps working unchanged.
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

];

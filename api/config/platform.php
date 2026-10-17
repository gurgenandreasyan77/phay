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

];

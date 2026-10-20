<?php

namespace App\Audience;

use App\Models\Tenant;

/** No social account can be connected yet: no figures. */
class NullSocialSource implements SocialSource
{
    public function accounts(Tenant $tenant, int $days): array
    {
        return [];
    }
}

<?php

namespace App\Audience;

use App\Models\Tenant;

/**
 * Where a business's social media figures come from (followers, reach,
 * engagement), once it connects an account. Today nothing is connected:
 * NullSocialSource answers. An Instagram or Facebook source implements this
 * and is bound in AppServiceProvider; the screens need no change.
 */
interface SocialSource
{
    /**
     * The business's connected accounts over the last $days days, or an
     * empty list when none is connected.
     *
     * @return list<array{network: string, handle: string, followers: int, followersChange: int, reach: int, engagement: float}>
     */
    public function accounts(Tenant $tenant, int $days): array;
}

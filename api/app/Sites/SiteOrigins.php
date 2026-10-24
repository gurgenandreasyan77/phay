<?php

namespace App\Sites;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;

/**
 * Browser origins of every active site, for CORS. Read on each API request,
 * so it is cached until a site changes.
 */
class SiteOrigins
{
    private const CACHE_KEY = 'sites.browser_origins';

    /** @return list<string> */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Site::query()
            ->where('is_active', true)
            ->get()
            ->flatMap(fn (Site $site) => $site->browserOrigins())
            ->unique()
            ->values()
            ->all());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

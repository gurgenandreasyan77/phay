<?php

namespace App\Sites;

use App\Models\Site;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells a business's sites to refresh their cache after its content changed.
 *
 * Edits only record the tags; they are sent once, after the response went
 * out (see AppServiceProvider), so a slow or broken site never slows down or
 * fails a save. A site that isn't reached still refreshes when its ISR
 * window runs out.
 */
class SiteRevalidator
{
    /** @var array<int, array<string, true>> tenant id => tag values */
    private array $pending = [];

    public function touch(int $tenantId, SiteCacheTag ...$tags): void
    {
        foreach ($tags as $tag) {
            $this->pending[$tenantId][$tag->value] = true;
        }
    }

    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $tenantId => $tags) {
            Site::query()->where('tenant_id', $tenantId)->get()
                ->filter(fn (Site $site) => $site->canRevalidate())
                ->each(fn (Site $site) => $this->send($site, array_keys($tags)));
        }
    }

    /**
     * @param  list<string>  $tags
     * @return array{ok: bool, status: ?int, error: ?string}
     */
    public function send(Site $site, array $tags): array
    {
        try {
            $response = Http::timeout(3)
                ->withHeaders(['X-Revalidate-Secret' => $site->revalidate_secret])
                ->asJson()
                ->post($site->revalidate_url, ['tags' => $tags]);
        } catch (ConnectionException $e) {
            Log::warning('Site cache refresh failed', ['site' => $site->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'status' => null, 'error' => $e->getMessage()];
        }

        if ($response->failed()) {
            Log::warning('Site cache refresh failed', ['site' => $site->id, 'status' => $response->status()]);
        }

        return ['ok' => $response->successful(), 'status' => $response->status(), 'error' => null];
    }
}

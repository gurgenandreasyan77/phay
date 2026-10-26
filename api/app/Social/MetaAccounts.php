<?php

namespace App\Social;

use App\Models\MetaDataDeletion;
use App\Models\SocialAccount;
use App\Models\SocialCompetitor;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Notifier;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Connecting a business's Facebook login (its Pages and the Instagram
 * accounts linked to them): the OAuth state, the long-lived token, which
 * accounts are synced, and forgetting everything on disconnect or when
 * Meta asks (deauthorize, data deletion). One login per business.
 */
class MetaAccounts
{
    /** How long Meta's login page may take. */
    private const STATE_MINUTES = 15;

    /** The owner hears this many days before the token ends that they should connect again. */
    public const WARN_DAYS = 7;

    public function __construct(
        private readonly MetaClient $meta,
        private readonly TenantContext $tenants,
        private readonly Notifier $notifier,
    ) {}

    public function isConfigured(): bool
    {
        return $this->meta->isConfigured();
    }

    public function connection(): ?SocialConnection
    {
        return SocialConnection::query()->first();
    }

    /** Where to send the person: Meta's login page, with a state only they can bring back. */
    public function authUrl(User $user): string
    {
        return $this->meta->authUrl(Crypt::encryptString(json_encode([
            'u' => $user->id,
            't' => $this->tenants->id(),
            'e' => now()->addMinutes(self::STATE_MINUTES)->timestamp,
            'n' => Str::random(16),
        ])));
    }

    /**
     * Back from Meta: the state must be this person's, for this business,
     * and recent; the code becomes a long-lived token, the Pages and their
     * Instagram accounts are listed.
     *
     * @throws InvalidMetaState
     * @throws MetaException
     */
    public function connect(User $user, string $code, string $state): SocialConnection
    {
        try {
            $payload = json_decode(Crypt::decryptString($state), true);
        } catch (DecryptException) {
            throw new InvalidMetaState;
        }

        if (! is_array($payload)
            || ($payload['u'] ?? null) !== $user->id
            || ($payload['t'] ?? null) !== $this->tenants->id()
            || ($payload['e'] ?? 0) < now()->timestamp) {
            throw new InvalidMetaState;
        }

        $token = $this->meta->exchangeCode($code);
        $me = $this->meta->me($token->accessToken);
        $scopes = $this->meta->grantedPermissions($token->accessToken);

        return DB::transaction(function () use ($user, $token, $me, $scopes) {
            $connection = $this->connection();

            // Another Facebook user than before: the old one's accounts and posts go.
            if ($connection !== null && $connection->meta_user_id !== $me['id']) {
                $connection->accounts()->delete();
            }

            $connection ??= new SocialConnection;
            $connection->fill([
                'user_id' => $user->id,
                'meta_user_id' => $me['id'],
                'meta_user_name' => $me['name'],
                'access_token' => $token->accessToken,
                'expires_at' => $token->expiresIn ? now()->addSeconds($token->expiresIn) : null,
                'scopes' => implode(',', $scopes),
                'revoked_at' => null,
                'expiry_warned_at' => null,
                'sync_error' => null,
            ])->save();

            $this->refreshAccounts($connection);

            return $connection;
        });
    }

    /**
     * The Pages (and their Instagram accounts) the login reads now: new
     * ones added (not selected, unless it is the only Page), changed ones
     * updated with a fresh Page token, gone ones deleted with their posts.
     *
     * @throws MetaException
     */
    public function refreshAccounts(SocialConnection $connection): void
    {
        $pages = $this->call($connection, fn () => $this->meta->pages($connection->access_token));
        $known = $connection->accounts()->get()->keyBy(fn (SocialAccount $a) => $a->network.':'.$a->external_id);
        $firstTime = $known->isEmpty();
        $seen = [];

        foreach ($pages as $page) {
            $only = $firstTime && count($pages) === 1;
            $seen[] = $this->upsert($connection, $known, SocialAccount::FACEBOOK, $page['id'], [
                'page_id' => $page['id'],
                'name' => $page['name'],
                'picture_url' => $page['picture'],
                'page_token' => $page['token'],
                'followers' => $page['followers'],
                'profile' => array_filter(['category' => $page['category'], 'link' => $page['link']]),
            ], $only);

            if ($page['instagram'] !== null) {
                $ig = $page['instagram'];
                $seen[] = $this->upsert($connection, $known, SocialAccount::INSTAGRAM, $ig['id'], [
                    'page_id' => $page['id'],
                    'username' => $ig['username'],
                    'name' => $ig['name'],
                    'picture_url' => $ig['picture'],
                    'page_token' => $page['token'],
                    'followers' => $ig['followers'],
                    'follows' => $ig['follows'],
                    'media_count' => $ig['mediaCount'],
                    'profile' => array_filter(['biography' => $ig['biography'], 'website' => $ig['website']]),
                ], $only);
            }
        }

        $known->each(function (SocialAccount $account, string $key) use ($seen) {
            if (! in_array($key, $seen, true)) {
                $account->delete();
            }
        });
    }

    /** @param Collection<string, SocialAccount> $known */
    private function upsert(SocialConnection $connection, $known, string $network, string $id, array $fields, bool $select): string
    {
        $key = $network.':'.$id;
        $account = $known->get($key) ?? new SocialAccount([
            'social_connection_id' => $connection->id,
            'network' => $network,
            'external_id' => $id,
            'selected' => $select,
        ]);
        $account->fill($fields)->save();

        return $key;
    }

    /**
     * Which accounts are synced. One no longer chosen loses its posts and
     * figures.
     *
     * @param  list<int>  $ids  ids of SocialAccount rows of this connection
     */
    public function choose(SocialConnection $connection, array $ids): void
    {
        foreach ($connection->accounts()->get() as $account) {
            $selected = in_array($account->id, $ids, true);

            if ($account->selected && ! $selected) {
                $account->posts()->delete();
                $account->days()->delete();
                $account->forceFill(['insights' => null, 'audience' => null, 'audience_synced_at' => null, 'synced_at' => null]);
            }

            $account->forceFill(['selected' => $selected])->save();
        }
    }

    /**
     * Forgets the login: the token, accounts, posts and what was read about
     * the watched accounts (their usernames stay on the list), and tells
     * Meta to drop the app's access.
     */
    public function disconnect(SocialConnection $connection): void
    {
        $this->meta->revoke($connection->access_token, $connection->meta_user_id);
        $this->forget($connection);
    }

    /**
     * Meta's deauthorize or data deletion callback for a Facebook user:
     * every business's connection made with that login is deleted, as on
     * disconnect. Returns the record whose code the person can check.
     */
    public function forgetMetaUser(string $metaUserId, string $kind): MetaDataDeletion
    {
        $connections = SocialConnection::withoutTenancy()->where('meta_user_id', $metaUserId)->get();

        foreach ($connections as $connection) {
            $tenant = Tenant::query()->find($connection->tenant_id);
            $tenant
                ? $this->tenants->run($tenant, fn () => $this->forget($connection))
                : $connection->delete();
        }

        return MetaDataDeletion::query()->create([
            'confirmation_code' => Str::lower(Str::random(20)),
            'kind' => $kind,
            'meta_user_hash' => hash('sha256', $metaUserId),
            'connections' => $connections->count(),
        ]);
    }

    private function forget(SocialConnection $connection): void
    {
        DB::transaction(function () use ($connection) {
            SocialCompetitor::withoutTenancy()->where('tenant_id', $connection->tenant_id)->get()
                ->each(fn (SocialCompetitor $competitor) => $competitor->forgetFigures());
            $connection->delete();
        });
    }

    /**
     * A token for an account's calls: its Page token (doesn't expire), else
     * the login's.
     */
    public function tokenFor(SocialAccount $account): string
    {
        return $account->page_token ?: $account->connection->access_token;
    }

    /**
     * Runs a Meta call; when Meta refuses the token the connection is marked
     * as needing a new login and its people are told.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     *
     * @throws MetaException
     */
    public function call(SocialConnection $connection, callable $call): mixed
    {
        try {
            return $call();
        } catch (MetaAuthException $e) {
            $this->markRevoked($connection);
            throw $e;
        }
    }

    public function markRevoked(SocialConnection $connection): void
    {
        if ($connection->revoked_at !== null) {
            return;
        }

        $connection->forceFill(['revoked_at' => now(), 'sync_error' => 'Meta no longer accepts the access.'])->save();

        $tenant = Tenant::query()->find($connection->tenant_id);
        if ($tenant) {
            $this->notifier->social(
                $tenant,
                'Instagram / Facebook կապը դադարեց աշխատել',
                'Meta-ն այլևս չի թույլատրում կարդալ էջերի տվյալները։ Միացրեք հաշիվը նորից «Սոցցանցեր» բաժնում։',
                'social-revoked:'.$connection->id.':'.now()->toDateString(),
            );
        }
    }

    /**
     * A long-lived token lasts about 60 days and Meta has no refresh token:
     * a few days before it ends the owner is asked to connect again (once
     * per token).
     */
    public function warnIfExpiring(SocialConnection $connection): void
    {
        if ($connection->revoked_at !== null
            || $connection->expires_at === null
            || $connection->expiry_warned_at !== null
            || $connection->expires_at->isAfter(now()->addDays(self::WARN_DAYS))) {
            return;
        }

        $tenant = Tenant::query()->find($connection->tenant_id);
        if ($tenant) {
            $this->notifier->social(
                $tenant,
                'Միացրեք Instagram / Facebook-ը նորից',
                sprintf('Meta-ի թույլտվությունն ավարտվում է %s-ին։ Սեղմեք «Միացնել նորից» «Սոցցանցեր» բաժնում, որպեսզի տվյալները շարունակեն թարմանալ։', $connection->expires_at->setTimezone($tenant->timezone ?: config('app.timezone'))->format('d.m.Y')),
                'social-expiry:'.$connection->id.':'.$connection->expires_at->toDateString(),
            );
        }

        $connection->forceFill(['expiry_warned_at' => now()])->save();
    }

    /** The permissions the module needs that the person unticked on Meta's page. */
    public function missingPermissions(SocialConnection $connection): array
    {
        $granted = $connection->grantedScopes();

        // Meta didn't say what was granted: nothing to point at.
        if ($granted === []) {
            return [];
        }

        return array_values(array_diff(['pages_show_list', 'pages_read_engagement', 'instagram_basic', 'instagram_manage_insights'], $granted));
    }
}

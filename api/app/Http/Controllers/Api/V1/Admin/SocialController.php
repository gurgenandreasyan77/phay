<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialCompetitor;
use App\Models\SocialConnection;
use App\Social\Competitors;
use App\Social\InvalidMetaState;
use App\Social\MetaAccounts;
use App\Social\MetaAuthException;
use App\Social\MetaException;
use App\Social\MetaRateLimited;
use App\Social\SocialReport;
use App\Social\SocialSync;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * The social module's screen: connecting the business's Facebook login
 * (its Pages and their Instagram accounts, read only), choosing which ones
 * are the business's, refreshing their figures, and the Instagram accounts
 * it watches. Disconnecting deletes everything read from Meta.
 */
class SocialController extends Controller
{
    public function __construct(
        private readonly MetaAccounts $accounts,
        private readonly SocialSync $sync,
        private readonly Competitors $competitors,
        private readonly SocialReport $report,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->state()]);
    }

    /** Meta's login page to send the person to. */
    public function connect(Request $request): JsonResponse
    {
        $this->ensureConfigured();

        return response()->json(['data' => ['url' => $this->accounts->authUrl($request->user())]]);
    }

    /** Back from Meta with a code: the login is connected, its Pages listed, the chosen ones read. */
    public function callback(Request $request): JsonResponse
    {
        $this->ensureConfigured();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:2000'],
            'state' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $connection = $this->accounts->connect($request->user(), $validated['code'], $validated['state']);
        } catch (InvalidMetaState) {
            return response()->json(['message' => 'The link has expired. Connect again.', 'code' => 'meta_state'], 422);
        } catch (MetaException $e) {
            report($e);

            return response()->json(['message' => 'Meta did not confirm the access.', 'code' => 'meta_error'], 502);
        }

        // With one Page it is chosen already: its figures come right away.
        if ($connection->accounts()->selected()->exists()) {
            try {
                $this->sync->sync($connection);
            } catch (MetaException $e) {
                report($e);
            }
        }

        return response()->json(['data' => $this->state()]);
    }

    /** Which Pages and Instagram accounts are the business's; then they are read. */
    public function choose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accounts' => ['present', 'array', 'max:100'],
            'accounts.*' => ['integer'],
        ]);

        $connection = $this->connection();
        $this->accounts->choose($connection, array_map('intval', $validated['accounts']));

        return $this->meta(function () use ($connection) {
            if ($connection->accounts()->selected()->exists()) {
                $this->sync->sync($connection);
            }

            return response()->json(['data' => $this->state()]);
        });
    }

    /** Reads the chosen accounts and the watched ones from Meta now. */
    public function sync(): JsonResponse
    {
        $connection = $this->connection();

        if ($this->sync->coolingDown($connection)) {
            return response()->json([
                'message' => 'The figures were refreshed a few minutes ago.',
                'code' => 'social_cooldown',
                'retryAfterMinutes' => SocialSync::COOLDOWN_MINUTES,
            ], 429);
        }

        return $this->meta(function () use ($connection) {
            $result = $this->sync->sync($connection, allCompetitors: true);

            return response()->json(['data' => ['result' => $result, ...$this->state()]]);
        });
    }

    /** Forgets the login and everything read from Meta; watched usernames stay. */
    public function disconnect(): Response
    {
        $connection = $this->accounts->connection();
        if ($connection !== null) {
            $this->accounts->disconnect($connection);
        }

        return response()->noContent();
    }

    public function addCompetitor(Request $request): JsonResponse
    {
        $validated = $request->validate(['username' => ['required', 'string', 'max:200']]);

        try {
            $competitor = $this->competitors->add($request->user(), $validated['username']);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => match ($e->getMessage()) {
                    'exists' => 'This account is on the list already.',
                    'limit' => 'At most '.Competitors::MAX.' accounts can be watched.',
                    'own' => 'This is the business\'s own account.',
                    default => 'This is not an Instagram username.',
                },
                'code' => 'competitor_'.$e->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $this->report->competitor($competitor, withPosts: true)], 201);
    }

    public function competitor(SocialCompetitor $socialCompetitor): JsonResponse
    {
        return response()->json(['data' => $this->report->competitor($socialCompetitor, withPosts: true)]);
    }

    public function removeCompetitor(SocialCompetitor $socialCompetitor): Response
    {
        $socialCompetitor->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        $connection = $this->accounts->connection();
        $accounts = $connection?->accounts()->orderBy('page_id')->orderByRaw("CASE WHEN network = 'facebook' THEN 0 ELSE 1 END")->get() ?? collect();

        return [
            'configured' => $this->accounts->isConfigured(),
            'connection' => $connection === null ? null : [
                'name' => $connection->meta_user_name,
                'connectedAt' => $connection->created_at?->toIso8601String(),
                'syncedAt' => $connection->synced_at?->toIso8601String(),
                'expiresAt' => $connection->expires_at?->toIso8601String(),
                'expiringSoon' => $connection->expires_at !== null && $connection->expires_at->isBefore(now()->addDays(MetaAccounts::WARN_DAYS)),
                // Meta refused the token: connect again.
                'needsReconnect' => $connection->revoked_at !== null,
                'missingPermissions' => $this->accounts->missingPermissions($connection),
                'syncError' => $connection->sync_error,
                'accounts' => $accounts->map(fn (SocialAccount $a) => [
                    'id' => $a->id,
                    'network' => $a->network,
                    'pageId' => $a->page_id,
                    'handle' => $a->handle(),
                    'name' => $a->name,
                    'picture' => $a->picture_url,
                    'followers' => $a->followers,
                    'selected' => $a->selected,
                ])->values(),
            ],
            'insights' => $this->report->selectedAccounts()->map(fn (SocialAccount $a) => $this->report->account($a, withPosts: true))->values(),
            'competitors' => SocialCompetitor::query()->orderBy('username')->get()->map(fn (SocialCompetitor $c) => $this->report->competitor($c))->values(),
            'comparison' => $this->report->comparison(),
            'maxCompetitors' => Competitors::MAX,
        ];
    }

    private function connection(): SocialConnection
    {
        $connection = $this->accounts->connection();
        abort_if($connection === null, 404, 'No Facebook login is connected.');

        return $connection;
    }

    /** Meta's errors as answers the screen understands. */
    private function meta(Closure $call): JsonResponse
    {
        try {
            return $call();
        } catch (MetaAuthException) {
            return response()->json(['message' => 'Meta no longer accepts the access. Connect again.', 'code' => 'meta_reconnect'], 409);
        } catch (MetaRateLimited) {
            return response()->json(['message' => 'Meta asks to wait: too many requests. The figures read so far are kept.', 'code' => 'meta_rate_limited'], 429);
        } catch (MetaException $e) {
            report($e);

            return response()->json(['message' => 'Meta did not answer.', 'code' => 'meta_error'], 502);
        }
    }

    private function ensureConfigured(): void
    {
        if (! $this->accounts->isConfigured()) {
            abort(response()->json(['message' => 'The Meta connection is not set up on the platform yet.', 'code' => 'meta_not_configured'], 503));
        }
    }
}

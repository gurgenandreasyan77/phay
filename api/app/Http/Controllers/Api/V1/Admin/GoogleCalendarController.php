<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Google\CalendarSync;
use App\Google\CalendarTables;
use App\Google\GoogleAccounts;
use App\Google\GoogleAuthException;
use App\Google\GoogleException;
use App\Google\InvalidGoogleState;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DataTableResource;
use App\Models\GoogleCalendar;
use App\Models\GoogleConnection;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The person's Google Calendar in the business's calendar, read only:
 * connecting an account (OAuth), choosing its calendars, syncing their
 * events and turning them into an order table (a new version each time
 * something changed, see CalendarTables).
 */
class GoogleCalendarController extends Controller
{
    public function __construct(
        private readonly GoogleAccounts $accounts,
        private readonly CalendarSync $sync,
        private readonly CalendarTables $tables,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->state($request)]);
    }

    /** Google's consent page to send the person to. */
    public function connect(Request $request): JsonResponse
    {
        $this->ensureConfigured();

        return response()->json(['data' => ['url' => $this->accounts->authUrl($request->user())]]);
    }

    /** Back from Google with a code: the account is connected, its calendars listed. */
    public function callback(Request $request): JsonResponse
    {
        $this->ensureConfigured();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:2000'],
            'state' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->accounts->connect($request->user(), $validated['code'], $validated['state']);
        } catch (InvalidGoogleState) {
            return response()->json(['message' => 'The link has expired. Connect again.', 'code' => 'google_state'], 422);
        } catch (GoogleException $e) {
            report($e);

            return response()->json(['message' => 'Google did not confirm the access.', 'code' => 'google_error'], 502);
        }

        return response()->json(['data' => $this->state($request)]);
    }

    /** Which of the account's calendars are synced. */
    public function choose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calendars' => ['present', 'array', 'max:100'],
            'calendars.*' => ['integer'],
        ]);

        $this->accounts->choose($this->connection($request), array_map('intval', $validated['calendars']));

        return response()->json(['data' => $this->state($request)]);
    }

    /** Reads the chosen calendars' new, changed and deleted events. */
    public function sync(Request $request): JsonResponse
    {
        $connection = $this->connection($request);

        return $this->google(function () use ($connection, $request) {
            $result = $this->sync->sync($connection);

            return response()->json(['data' => ['result' => $result, ...$this->state($request)]]);
        });
    }

    /** What «turn into a table» would do now: counts for the confirmation. */
    public function preview(): JsonResponse
    {
        return response()->json(['data' => $this->previewData()]);
    }

    /** The order table from the synced events: made, or a new version of it. */
    public function makeTable(Request $request): JsonResponse
    {
        $preview = $this->tables->preview();
        abort_if($preview['table'] === null && $preview['spread'] === [] && $preview['events'] === 0, 422, 'There are no synced events yet.');

        $result = $this->tables->make($request->user());

        return response()->json(['data' => [
            'table' => (new DataTableResource($result['table']->loadCount('rows')))->resolve($request),
            'created' => $result['created'],
            'previous' => $result['previous'] ? ['id' => $result['previous']->id, 'name' => $result['previous']->name] : null,
            'spread' => $result['spread'],
            'counts' => collect($result)->only(['events', 'new', 'changed', 'removed', 'keptEdited', 'upToDate']),
        ]], $result['created'] ? 201 : 200);
    }

    /** Forgets the person's Google account; tables made from it stay. */
    public function disconnect(Request $request): Response
    {
        $connection = $this->accounts->connection($request->user());
        if ($connection !== null) {
            $this->accounts->disconnect($connection);
        }

        return response()->noContent();
    }

    private function state(Request $request): array
    {
        $connection = $this->accounts->connection($request->user());
        $calendars = $connection?->calendars()->withCount('events')->orderByDesc('is_primary')->orderBy('name')->get();

        return [
            'configured' => $this->accounts->isConfigured(),
            'connection' => $connection === null ? null : [
                'email' => $connection->email,
                'connectedAt' => $connection->created_at?->toIso8601String(),
                'syncedAt' => $connection->synced_at?->toIso8601String(),
                // Google refused the tokens: connect again.
                'needsReconnect' => $connection->revoked_at !== null,
                'calendars' => $calendars->map(fn (GoogleCalendar $calendar) => [
                    'id' => $calendar->id,
                    'name' => $calendar->name,
                    'color' => $calendar->color,
                    'primary' => $calendar->is_primary,
                    'selected' => $calendar->selected,
                    'syncedAt' => $calendar->synced_at?->toIso8601String(),
                    'events' => $calendar->events_count,
                ])->values(),
            ],
            'pending' => $this->previewData(),
        ];
    }

    private function previewData(): array
    {
        $preview = $this->tables->preview();

        return [
            'table' => $preview['table'] ? [
                'id' => $preview['table']->id,
                'name' => $preview['table']->name,
                'createdAt' => $preview['table']->created_at?->toIso8601String(),
            ] : null,
            // The Google table was split into these (a month's each): events sync into them.
            'spread' => $preview['spread'],
            ...collect($preview)->only(['events', 'new', 'changed', 'removed', 'keptEdited', 'upToDate'])->all(),
        ];
    }

    private function connection(Request $request): GoogleConnection
    {
        $connection = $this->accounts->connection($request->user());
        abort_if($connection === null, 404, 'Google Calendar is not connected.');

        return $connection;
    }

    private function ensureConfigured(): void
    {
        if (! $this->accounts->isConfigured()) {
            abort(response()->json([
                'message' => 'Google Calendar is not set up on this platform.',
                'code' => 'google_not_configured',
            ], 503));
        }
    }

    /** Google's errors as answers the admin understands. */
    private function google(Closure $call): JsonResponse
    {
        try {
            return $call();
        } catch (GoogleAuthException) {
            return response()->json(['message' => 'Google access was revoked. Connect again.', 'code' => 'google_reconnect'], 409);
        } catch (GoogleException $e) {
            report($e);

            return response()->json(['message' => 'Google Calendar did not answer. Try again later.', 'code' => 'google_error'], 502);
        }
    }
}

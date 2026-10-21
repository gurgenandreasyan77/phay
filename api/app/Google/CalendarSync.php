<?php

namespace App\Google;

use App\Models\GoogleCalendar;
use App\Models\GoogleConnection;
use App\Models\GoogleEvent;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Copies the events of a person's chosen Google calendars into the
 * business (google_events), only reading Google. The first sync of a
 * calendar reads a year back and a year ahead; later ones ask Google only
 * for what changed since (its sync token), deleted events included. When
 * Google forgets the token, the calendar is read in full again and events
 * gone from it are removed.
 */
class CalendarSync
{
    /** How far back and ahead a full sync reads. */
    public const WINDOW_DAYS = 365;

    /** 250 events a page: a calendar of a very busy business, at most. */
    private const MAX_PAGES = 40;

    public function __construct(
        private readonly GoogleCalendarClient $google,
        private readonly GoogleAccounts $accounts,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @return array{calendars: int, added: int, updated: int, removed: int}
     *
     * @throws GoogleAuthException when the access was revoked (connect again)
     * @throws GoogleException
     */
    public function sync(GoogleConnection $connection): array
    {
        $this->accounts->refreshCalendars($connection);

        $totals = ['calendars' => 0, 'added' => 0, 'updated' => 0, 'removed' => 0];

        foreach ($connection->calendars()->where('selected', true)->orderBy('id')->get() as $calendar) {
            $result = $this->syncCalendar($connection, $calendar);
            $totals['calendars']++;
            foreach (['added', 'updated', 'removed'] as $key) {
                $totals[$key] += $result[$key];
            }
        }

        $connection->forceFill(['synced_at' => now()])->save();

        return $totals;
    }

    /** @return array{added: int, updated: int, removed: int} */
    private function syncCalendar(GoogleConnection $connection, GoogleCalendar $calendar): array
    {
        if (filled($calendar->sync_token)) {
            try {
                return $this->read($connection, $calendar, ['syncToken' => $calendar->sync_token], full: false);
            } catch (GoogleSyncTokenExpired) {
                // Read again in full below.
            }
        }

        return $this->read($connection, $calendar, [
            'timeMin' => now()->subDays(self::WINDOW_DAYS)->toRfc3339String(),
            'timeMax' => now()->addDays(self::WINDOW_DAYS)->toRfc3339String(),
        ], full: true);
    }

    /** @return array{added: int, updated: int, removed: int} */
    private function read(GoogleConnection $connection, GoogleCalendar $calendar, array $query, bool $full): array
    {
        $timezone = $this->tenants->current()->timezone ?: config('app.timezone');
        $pages = [];
        $pageToken = null;
        $syncToken = null;

        // Every page first, then the changes at once: a sync cut short changes nothing.
        do {
            $page = $this->accounts->withToken($connection, fn (string $token) => $this->google->events(
                $token,
                $calendar->calendar_id,
                [...$query, 'pageToken' => $pageToken],
            ));
            $pages[] = $page->items;
            $pageToken = $page->nextPageToken;
            $syncToken = $page->nextSyncToken ?? $syncToken;
        } while ($pageToken !== null && count($pages) < self::MAX_PAGES);

        return DB::transaction(function () use ($calendar, $pages, $full, $timezone, $syncToken, $pageToken) {
            $known = $calendar->events()->get(['id', 'event_id', 'google_updated'])->keyBy('event_id');
            $seen = [];
            $counts = ['added' => 0, 'updated' => 0, 'removed' => 0];

            foreach (array_merge(...$pages) as $item) {
                $id = (string) ($item['id'] ?? '');
                $data = EventData::from($item, $timezone);
                $event = $known->get($id);

                if ($data === null) {
                    // Cancelled (deleted in Google, or one instance of a series).
                    if ($event !== null && $id !== '') {
                        $event->delete();
                        $known->forget($id);
                        $counts['removed']++;
                    }

                    continue;
                }

                $seen[$id] = true;

                if ($event === null) {
                    $event = $calendar->events()->create($data);
                    $known->put($id, $event);
                    $counts['added']++;
                } elseif ($event->google_updated !== $data['google_updated']) {
                    GoogleEvent::query()->whereKey($event->id)->update([...$data, 'updated_at' => now()]);
                    $counts['updated']++;
                }
            }

            // A full read lists every event of the window: the others are gone.
            // Not when it was cut short (more pages than read).
            if ($full && $pageToken === null) {
                $from = now()->subDays(self::WINDOW_DAYS - 1)->format('Y-m-d H:i:s');
                $to = now()->addDays(self::WINDOW_DAYS - 1)->format('Y-m-d H:i:s');
                $gone = $known->keys()->diff(array_keys($seen));
                if ($gone->isNotEmpty()) {
                    $counts['removed'] += $calendar->events()
                        ->whereIn('event_id', $gone->all())
                        ->where('starts_at', '>=', $from)
                        ->where('starts_at', '<', $to)
                        ->delete();
                }
            }

            $calendar->forceFill([
                // Only a complete read gives a token to continue from.
                'sync_token' => $pageToken === null ? $syncToken : null,
                'synced_at' => now(),
            ])->save();

            return $counts;
        });
    }
}

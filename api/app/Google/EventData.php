<?php

namespace App\Google;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * A Google Calendar event as the fields kept of it (google_events), its
 * times in the business's local time zone. An all-day event starts at
 * midnight of its day.
 */
final class EventData
{
    /**
     * @return ?array{event_id: string, summary: ?string, description: ?string, location: ?string, starts_at: string, ends_at: ?string, all_day: bool, html_link: ?string, google_updated: ?string}
     *                                                                                                                                                                                              null for a cancelled event or one without a start
     */
    public static function from(array $item, string $timezone): ?array
    {
        if (! filled($item['id'] ?? null) || ($item['status'] ?? 'confirmed') === 'cancelled') {
            return null;
        }

        $start = self::moment($item['start'] ?? null, $timezone);
        if ($start === null) {
            return null;
        }
        $end = self::moment($item['end'] ?? null, $timezone);

        return [
            'event_id' => mb_substr((string) $item['id'], 0, 255),
            'summary' => self::text($item['summary'] ?? null, 500),
            'description' => self::text(self::plain($item['description'] ?? null), 5000),
            'location' => self::text($item['location'] ?? null, 500),
            'starts_at' => $start[0],
            'ends_at' => $end[0] ?? null,
            'all_day' => $start[1],
            'html_link' => self::text($item['htmlLink'] ?? null, 1000),
            'google_updated' => self::text($item['updated'] ?? null, 40),
        ];
    }

    /** @return ?array{0: string, 1: bool} "Y-m-d H:i:s" local, and whether it's a whole day */
    private static function moment(mixed $time, string $timezone): ?array
    {
        if (! is_array($time)) {
            return null;
        }

        try {
            if (filled($time['dateTime'] ?? null)) {
                return [CarbonImmutable::parse($time['dateTime'])->setTimezone($timezone)->format('Y-m-d H:i:s'), false];
            }
            if (is_string($time['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $time['date'])) {
                return ["{$time['date']} 00:00:00", true];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /** Google keeps a description as simple HTML: lines and plain text are kept. */
    private static function plain(mixed $html): ?string
    {
        if (! is_string($html)) {
            return null;
        }

        $text = preg_replace('#<br\s*/?>|</p>|</div>|</li>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text));
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $max);
    }
}

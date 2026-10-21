<?php

namespace App\Google;

use App\Enums\ColumnRole;
use App\Models\GoogleEvent;

/**
 * What a table row takes from a Google event, by column role: the start as
 * the date, the title as the service, the place as the address, and from
 * the description lines like «Հաճախորդ: Անի», «Հեռախոս: 091 23 45 67»,
 * «Գին: 9900», «Կատարող: Արամ» (any wording ColumnRole::guess knows, in
 * Armenian, Russian or English). A phone or an amount with ֏/դր/AMD in the
 * text is found without a label too. The rest of the description is the note.
 */
final class EventFields
{
    /** The roles of a table made from the calendars, in its columns' order. */
    public const ROLES = [
        ColumnRole::Date,
        ColumnRole::Item,
        ColumnRole::Customer,
        ColumnRole::Phone,
        ColumnRole::Address,
        ColumnRole::Performer,
        ColumnRole::Amount,
        ColumnRole::Note,
    ];

    /** @return array<string, ?string> raw values by role value */
    public static function of(GoogleEvent $event): array
    {
        $lines = preg_split('/\n/', (string) $event->description) ?: [];
        $labelled = [];
        $rest = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*([^:：՝\n]{2,40}?)\s*[:：՝]\s*(.+?)\s*$/u', $line, $m)) {
                $role = ColumnRole::guess($m[1]);
                $role = $role === ColumnRole::UnitPrice ? ColumnRole::Amount : $role;
                if ($role !== null && in_array($role, self::ROLES, true) && ! in_array($role, [ColumnRole::Date, ColumnRole::Item], true) && ! isset($labelled[$role->value])) {
                    $labelled[$role->value] = $m[2];

                    continue;
                }
            }
            $rest[] = $line;
        }

        $text = trim(($event->summary ?? '')."\n".implode("\n", $rest));
        $note = trim(implode("\n", $rest));

        return [
            ColumnRole::Date->value => substr((string) $event->getRawOriginal('starts_at'), 0, 16),
            ColumnRole::Item->value => $event->summary ?? 'Առանց անվանման',
            ColumnRole::Customer->value => $labelled[ColumnRole::Customer->value] ?? null,
            ColumnRole::Phone->value => $labelled[ColumnRole::Phone->value] ?? self::phone($text),
            ColumnRole::Address->value => $event->location ?? $labelled[ColumnRole::Address->value] ?? null,
            ColumnRole::Performer->value => $labelled[ColumnRole::Performer->value] ?? null,
            ColumnRole::Amount->value => isset($labelled[ColumnRole::Amount->value])
                ? self::withoutCurrency($labelled[ColumnRole::Amount->value])
                : self::amount($text),
            ColumnRole::Note->value => $note === '' ? null : $note,
        ];
    }

    /** A phone number in the text: 8 to 15 digits, maybe with +, spaces, dashes, brackets. */
    private static function phone(string $text): ?string
    {
        if (preg_match_all('/\+?\(?\d[\d\s\-()]{6,18}\d/u', $text, $matches)) {
            foreach ($matches[0] as $match) {
                $digits = strlen(preg_replace('/\D/', '', $match));
                if ($digits >= 8 && $digits <= 15) {
                    return trim($match);
                }
            }
        }

        return null;
    }

    /** Money written with its currency: «9 900 ֏», «9900 դր», «AMD 9,900», «9900 драм». */
    private static function amount(string $text): ?string
    {
        $number = '((?:\d{1,3}(?:[\s\x{00A0},.]\d{3})+|\d+)(?:[.,]\d{1,2})?)';

        if (preg_match('/'.$number.'\s*'.self::CURRENCY.'(?!\p{L})/iu', $text, $m)
            || preg_match('/(?<!\p{L})'.self::CURRENCY.'\s*'.$number.'/iu', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    private const CURRENCY = '(?:֏|դրամ|դր\.?|amd|драм(?:а|ов)?|др\.?)';

    /** «9900 դրամ» as the number the money column reads. */
    private static function withoutCurrency(string $value): string
    {
        return trim(preg_replace('/'.self::CURRENCY.'/iu', '', $value));
    }
}

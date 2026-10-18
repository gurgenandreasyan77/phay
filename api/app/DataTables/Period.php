<?php

namespace App\DataTables;

use App\Enums\TablePeriod;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * One day, ISO week, month, quarter or year: where a date sits in the
 * business's calendar. 18 November 2026 is the day 2026-11-18, ISO week
 * 2026-W47 (16-22 November), month 2026-11, quarter 2026-Q4 and year 2026.
 * Dates are the business's local time, as rows store them (occurred_at).
 */
final readonly class Period
{
    /** Armenian month names: nominative (a table's name) and genitive (a day). */
    private const MONTHS = ['Հունվար', 'Փետրվար', 'Մարտ', 'Ապրիլ', 'Մայիս', 'Հունիս', 'Հուլիս', 'Օգոստոս', 'Սեպտեմբեր', 'Հոկտեմբեր', 'Նոյեմբեր', 'Դեկտեմբեր'];

    private function __construct(
        public TablePeriod $kind,
        /** The first day of the period, at midnight. */
        public CarbonImmutable $start,
    ) {}

    /** The period of this kind a date (or "Y-m-d[ H:i[:s]]") falls in. */
    public static function of(TablePeriod $kind, DateTimeInterface|string $date): self
    {
        if (! $kind->isCalendar()) {
            throw new InvalidArgumentException('A custom table has no period.');
        }

        $date = CarbonImmutable::parse(is_string($date) ? substr($date, 0, 10) : $date->format('Y-m-d'))->startOfDay();

        return new self($kind, match ($kind) {
            TablePeriod::Day => $date,
            TablePeriod::Week => $date->startOfWeek(CarbonImmutable::MONDAY),
            TablePeriod::Month => $date->startOfMonth(),
            TablePeriod::Quarter => $date->startOfQuarter(),
            TablePeriod::Year => $date->startOfYear(),
        });
    }

    /** The day after the period's last one: rows are in it when start <= occurred_at < next. */
    public function next(): CarbonImmutable
    {
        return match ($this->kind) {
            TablePeriod::Day => $this->start->addDay(),
            TablePeriod::Week => $this->start->addWeek(),
            TablePeriod::Month => $this->start->addMonthNoOverflow(),
            TablePeriod::Quarter => $this->start->addMonthsNoOverflow(3),
            TablePeriod::Year => $this->start->addYear(),
        };
    }

    public function end(): CarbonImmutable
    {
        return $this->next()->subDay();
    }

    public function contains(DateTimeInterface|string $date): bool
    {
        return self::of($this->kind, $date)->key() === $this->key();
    }

    /** The period before or after this one. */
    public function shift(int $by): self
    {
        return self::of($this->kind, match ($this->kind) {
            TablePeriod::Day => $this->start->addDays($by),
            TablePeriod::Week => $this->start->addWeeks($by),
            TablePeriod::Month => $this->start->addMonthsNoOverflow($by),
            TablePeriod::Quarter => $this->start->addMonthsNoOverflow(3 * $by),
            TablePeriod::Year => $this->start->addYears($by),
        });
    }

    /** "2026-11-18", "2026-W47", "2026-11", "2026-Q4", "2026". */
    public function key(): string
    {
        return match ($this->kind) {
            TablePeriod::Day => $this->start->format('Y-m-d'),
            TablePeriod::Week => sprintf('%04d-W%02d', $this->start->isoWeekYear, $this->start->isoWeek),
            TablePeriod::Month => $this->start->format('Y-m'),
            TablePeriod::Quarter => $this->start->format('Y').'-Q'.$this->start->quarter,
            TablePeriod::Year => $this->start->format('Y'),
        };
    }

    /** "18 նոյեմբերի 2026", "47-րդ շաբաթ 2026 (16.11–22.11)", "Նոյեմբեր 2026", "4-րդ եռամսյակ 2026", "2026". */
    public function label(): string
    {
        $s = $this->start;
        $ordinal = fn (int $n) => $n === 1 ? '1-ին' : "{$n}-րդ";

        return match ($this->kind) {
            TablePeriod::Day => $s->day.' '.mb_strtolower(self::MONTHS[$s->month - 1]).'ի '.$s->year,
            TablePeriod::Week => $ordinal($s->isoWeek).' շաբաթ '.$s->isoWeekYear.' ('.$s->format('d.m').'–'.$this->end()->format('d.m').')',
            TablePeriod::Month => self::MONTHS[$s->month - 1].' '.$s->year,
            TablePeriod::Quarter => $ordinal($s->quarter).' եռամսյակ '.$s->year,
            TablePeriod::Year => (string) $s->year,
        };
    }

    /** @return array{kind: string, key: string, label: string, start: string, end: string} */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'key' => $this->key(),
            'label' => $this->label(),
            'start' => $this->start->toDateString(),
            'end' => $this->end()->toDateString(),
        ];
    }

    /**
     * Every level a date belongs to at once: an order of 18 November 2026 is
     * in 2026, its 4th quarter, November, ISO week 47 and that day.
     *
     * @return array{year: int, quarter: int, month: int, week: int, weekYear: int, day: string}
     */
    public static function tags(DateTimeInterface|string $date): array
    {
        $d = CarbonImmutable::parse(is_string($date) ? substr($date, 0, 10) : $date->format('Y-m-d'));

        return [
            'year' => $d->year,
            'quarter' => $d->quarter,
            'month' => $d->month,
            // ISO week: Monday to Sunday; the first week of a year has its first Thursday.
            'week' => $d->isoWeek,
            'weekYear' => $d->isoWeekYear,
            'day' => $d->toDateString(),
        ];
    }
}

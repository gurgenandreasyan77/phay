// Days of the calendar as "Y-m-d" strings, counted in UTC so the server's
// and the browser's time zones never shift a day.

export type CalendarView = "month" | "week";

function toDate(day: string): Date {
  return new Date(`${day}T00:00:00Z`);
}

function toDay(date: Date): string {
  return date.toISOString().slice(0, 10);
}

export function isDay(value: string | undefined): value is string {
  return Boolean(value && /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(toDate(value).getTime()));
}

/** Today in the business's time zone (Armenia). */
export function today(timeZone = "Asia/Yerevan"): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone, year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());
}

export function addDays(day: string, days: number): string {
  const date = toDate(day);
  date.setUTCDate(date.getUTCDate() + days);
  return toDay(date);
}

/** The same day a month later or earlier, the 31st becoming the month's last day. */
export function addMonths(day: string, months: number): string {
  const date = toDate(day);
  const target = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + months, 1));
  const last = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
  target.setUTCDate(Math.min(date.getUTCDate(), last));
  return toDay(target);
}

/** 1 for Monday ... 7 for Sunday: weeks start on Monday in Armenia. */
export function weekday(day: string): number {
  return toDate(day).getUTCDay() || 7;
}

/** The days shown: whole weeks covering the month, or the week of the day. */
export function visibleDays(day: string, view: CalendarView): string[] {
  const start = view === "week" ? day : `${day.slice(0, 8)}01`;
  const first = addDays(start, 1 - weekday(start));
  const last =
    view === "week"
      ? addDays(first, 6)
      : (() => {
          const end = toDay(new Date(Date.UTC(Number(day.slice(0, 4)), Number(day.slice(5, 7)), 0)));
          return addDays(end, 7 - weekday(end));
        })();

  const days: string[] = [];
  for (let d = first; d <= last; d = addDays(d, 1)) days.push(d);
  return days;
}

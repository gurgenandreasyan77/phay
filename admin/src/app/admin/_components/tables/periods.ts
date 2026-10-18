// Periods of time a table is kept for and a row is filed under, as the API
// counts them (App\DataTables\Period): days, ISO weeks (Monday to Sunday),
// months, quarters and years of "Y-m-d" dates. Shared by server and client.
import type { PeriodDto, PeriodKind, PeriodTagsDto, TablePeriod } from "@/lib/adminApi";

export const PERIOD_KINDS: PeriodKind[] = ["day", "week", "month", "quarter", "year"];

export const TABLE_PERIOD_LABELS: Record<TablePeriod, string> = {
  day: "Օրական",
  week: "Շաբաթական",
  month: "Ամսական",
  quarter: "Եռամսյակային",
  year: "Տարեկան",
  custom: "Ազատ ժամանակահատված",
};

/** One period of the kind: "Օր", "Շաբաթ"… for a filter. */
export const PERIOD_UNIT_LABELS: Record<PeriodKind, string> = {
  day: "Օր",
  week: "Շաբաթ",
  month: "Ամիս",
  quarter: "Եռամսյակ",
  year: "Տարի",
};

const MONTHS = ["Հունվար", "Փետրվար", "Մարտ", "Ապրիլ", "Մայիս", "Հունիս", "Հուլիս", "Օգոստոս", "Սեպտեմբեր", "Հոկտեմբեր", "Նոյեմբեր", "Դեկտեմբեր"];

function parse(date: string): Date {
  const [y, m, d] = date.slice(0, 10).split("-").map(Number);
  return new Date(Date.UTC(y, m - 1, d));
}

function format(date: Date): string {
  return date.toISOString().slice(0, 10);
}

function addDays(date: Date, days: number): Date {
  return new Date(date.getTime() + days * 86_400_000);
}

const pad = (n: number) => String(n).padStart(2, "0");
const ordinal = (n: number) => (n === 1 ? "1-ին" : `${n}-րդ`);

/** ISO week and its year: 1 January 2027 is in week 53 of 2026. */
function isoWeek(date: Date): { week: number; weekYear: number } {
  const thursday = addDays(date, 3 - ((date.getUTCDay() + 6) % 7));
  const weekYear = thursday.getUTCFullYear();
  const week = 1 + Math.floor((thursday.getTime() - Date.UTC(weekYear, 0, 1)) / (7 * 86_400_000));
  return { week, weekYear };
}

export function isDate(value: string): boolean {
  return /^\d{4}-\d{2}-\d{2}/.test(value);
}

export function tagsOf(date: string): PeriodTagsDto {
  const d = parse(date);
  const month = d.getUTCMonth() + 1;
  return { year: d.getUTCFullYear(), quarter: Math.ceil(month / 3), month, ...isoWeek(d), day: format(d) };
}

/** The period of this kind a "Y-m-d" date is in. */
export function periodOf(kind: PeriodKind, date: string): PeriodDto {
  const d = parse(date);
  const y = d.getUTCFullYear();
  const m = d.getUTCMonth();
  let start: Date;
  let next: Date;

  switch (kind) {
    case "day":
      start = d;
      next = addDays(d, 1);
      break;
    case "week":
      start = addDays(d, -((d.getUTCDay() + 6) % 7));
      next = addDays(start, 7);
      break;
    case "month":
      start = new Date(Date.UTC(y, m, 1));
      next = new Date(Date.UTC(y, m + 1, 1));
      break;
    case "quarter":
      start = new Date(Date.UTC(y, m - (m % 3), 1));
      next = new Date(Date.UTC(y, m - (m % 3) + 3, 1));
      break;
    case "year":
      start = new Date(Date.UTC(y, 0, 1));
      next = new Date(Date.UTC(y + 1, 0, 1));
      break;
  }

  const end = addDays(next, -1);
  const { week, weekYear } = isoWeek(start);
  const sm = start.getUTCMonth();
  const sy = start.getUTCFullYear();
  const dm = (x: Date) => `${pad(x.getUTCDate())}.${pad(x.getUTCMonth() + 1)}`;

  const [key, label] = {
    day: [format(start), `${start.getUTCDate()} ${MONTHS[sm].toLowerCase()}ի ${sy}`],
    week: [`${weekYear}-W${pad(week)}`, `${ordinal(week)} շաբաթ ${weekYear} (${dm(start)}–${dm(end)})`],
    month: [`${sy}-${pad(sm + 1)}`, `${MONTHS[sm]} ${sy}`],
    quarter: [`${sy}-Q${Math.floor(sm / 3) + 1}`, `${ordinal(Math.floor(sm / 3) + 1)} եռամսյակ ${sy}`],
    year: [String(sy), String(sy)],
  }[kind];

  return { kind, key, label, start: format(start), end: format(end) };
}

/** The period before (-1) or after (+1) the one this date is in. */
export function shiftPeriod(kind: PeriodKind, date: string, by: -1 | 1): PeriodDto {
  const current = periodOf(kind, date);
  return periodOf(kind, by < 0 ? format(addDays(parse(current.start), -1)) : format(addDays(parse(current.end), 1)));
}

export function contains(period: Pick<PeriodDto, "start" | "end">, date: string): boolean {
  const day = date.slice(0, 10);
  return day >= period.start && day <= period.end;
}

/** "2026 · 4-րդ եռամսյակ · Նոյեմբեր · 47-րդ շաբաթ · 18.11": everything a date is filed under. */
export function filingLabel(date: string): string {
  const t = tagsOf(date);
  return [
    String(t.year),
    `${ordinal(t.quarter)} եռամսյակ`,
    MONTHS[t.month - 1],
    `${ordinal(t.week)} շաբաթ${t.weekYear !== t.year ? ` (${t.weekYear})` : ""}`,
    `${t.day.slice(8, 10)}.${t.day.slice(5, 7)}`,
  ].join(" · ");
}

/** Today in Yerevan, "Y-m-d" (the same on the server and in the browser). */
export function today(): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Yerevan" }).format(new Date());
}

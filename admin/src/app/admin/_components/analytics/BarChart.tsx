import type { CSSProperties } from "react";
import { cn } from "@/ui";
import styles from "./charts.module.css";
import { formatNumber } from "../tables/shared";

export type ChartBar = {
  key: string;
  /** Under the bar, short: "17 հոկ", "Երկ", "9:00". */
  label: string;
  /** Head of the hover card, in full: "17 հոկտեմբերի, շաբաթ". */
  title: string;
  value: number;
  /** More lines of the hover card, such as the number of sales that day. */
  details?: string[];
};

const MONTHS = ["հնվ", "փտվ", "մրտ", "ապր", "մյս", "հնս", "հլս", "օգս", "սեպ", "հոկ", "նոյ", "դեկ"];
const MONTH_NAMES = [
  "Հունվար",
  "Փետրվար",
  "Մարտ",
  "Ապրիլ",
  "Մայիս",
  "Հունիս",
  "Հուլիս",
  "Օգոստոս",
  "Սեպտեմբեր",
  "Հոկտեմբեր",
  "Նոյեմբեր",
  "Դեկտեմբեր",
];
// "17 հոկտեմբերի": a day of a month takes the genitive.
const MONTHS_OF = MONTH_NAMES.map((name) => `${name.toLowerCase()}ի`);
// Monday first, as the backend counts them (1 = Monday).
export const WEEKDAYS = ["Երկուշաբթի", "Երեքշաբթի", "Չորեքշաբթի", "Հինգշաբթի", "Ուրբաթ", "Շաբաթ", "Կիրակի"];
const WEEKDAYS_SHORT = ["Երկ", "Երք", "Չրք", "Հնգ", "Ուրբ", "Շբթ", "Կիր"];

/** "2026-10-17" → "17 հոկ", "2026-10" → "հոկ". */
export function bucketLabel(date: string): string {
  const [, month, day] = date.split("-");
  const monthName = MONTHS[Number(month) - 1] ?? month;
  return day ? `${Number(day)} ${monthName}` : monthName;
}

/** "2026-10-17" → "17 հոկտեմբերի, շաբաթ", "2026-10" → "Հոկտեմբեր 2026". */
export function bucketTitle(date: string): string {
  const [year, month, day] = date.split("-").map(Number);
  if (!day) return `${MONTH_NAMES[month - 1] ?? month} ${year}`;
  const weekday = (new Date(Date.UTC(year, month - 1, day)).getUTCDay() + 6) % 7;
  return `${day} ${MONTHS_OF[month - 1] ?? month}, ${WEEKDAYS[weekday].toLowerCase()}`;
}

export function weekdayShort(day: number): string {
  return WEEKDAYS_SHORT[day - 1] ?? String(day);
}

/** Bars of a dated series: "17 հոկ" under, the full date and weekday on hover. */
export function dateBars<P extends { date: string }>(
  points: P[],
  value: (point: P) => number,
  details?: (point: P) => string[],
): ChartBar[] {
  return points.map((point) => ({
    key: point.date,
    label: bucketLabel(point.date),
    title: bucketTitle(point.date),
    value: value(point),
    details: details?.(point),
  }));
}

/** 12500 → "12,5 հզ", 2400000 → "2,4 մլն": fits on an axis or above a narrow bar. */
export function compact(value: number): string {
  const size = Math.abs(value);
  if (size >= 1_000_000) return `${formatNumber(Math.round(value / 100_000) / 10)} մլն`;
  if (size >= 10_000) return `${formatNumber(Math.round(value / 1_000))} հզ`;
  if (size >= 1_000) return `${formatNumber(Math.round(value / 100) / 10)} հզ`;
  return formatNumber(Math.round(value * 10) / 10);
}

/** A round step so the axis reads 0, 5 000, 10 000 and not 0, 4 731, 9 462. */
function niceStep(range: number, ticks: number): number {
  const raw = range / ticks;
  const power = 10 ** Math.floor(Math.log10(raw));
  const step = [1, 2, 2.5, 5, 10].find((m) => m * power >= raw) ?? 10;
  return step * power;
}

function scale(values: number[]): { low: number; high: number; ticks: number[] } {
  const max = Math.max(...values, 0);
  const min = Math.min(...values, 0);
  // Counts are whole: an axis of 0, 0.5, 1 for one order a day reads wrong.
  const whole = values.every(Number.isInteger);
  let step = niceStep(max - min || 1, 4);
  if (whole) step = Math.max(1, Math.ceil(step));
  const low = Math.floor(min / step) * step;
  const high = Math.max(Math.ceil(max / step) * step, low + step);
  const ticks: number[] = [];
  for (let tick = low; tick <= high + step / 2; tick += step) ticks.push(Math.round(tick * 1e6) / 1e6);
  return { low, high, ticks };
}

/**
 * Bars with a value axis, gridlines and a card on hover (or tap) with the
 * full date and the value. Plain CSS, so it renders on the server.
 */
export function BarChart({
  bars,
  format,
  axis = compact,
  color = "var(--color-primary)",
}: {
  bars: ChartBar[];
  /** The value in full, for the hover card: "12 500 դր.", "3 վաճառք". */
  format: (n: number) => string;
  /** The value on the axis and above the bars, short. */
  axis?: (n: number) => string;
  /** The bars' colour; losses stay red. */
  color?: string;
}) {
  if (bars.every((bar) => bar.value === 0)) {
    return <p className="py-10 text-center text-sm text-ink-muted">Այս ժամանակահատվածում տվյալներ դեռ չկան։</p>;
  }

  const { low, high, ticks } = scale(bars.map((bar) => bar.value));
  const y = (value: number) => ((value - low) / (high - low)) * 100;
  const zero = y(0);
  const labelEvery = bars.length <= 12 ? 1 : Math.ceil(bars.length / 7);
  const withValues = bars.length <= 12;
  // Twelve figures side by side need room: in a narrow card (a phone) they
  // overlap, so above the bars only up to 7, the rest on tap.
  const valuesWhenNarrow = bars.length <= 7;
  // Near the edges the card leans inwards, or it would be cut off.
  const edge = bars.length < 5 ? 0 : Math.max(1, Math.floor(bars.length / 6));

  return (
    <div data-chart className="@container select-none">
      <div className="flex">
        <div className="relative mr-2 h-48 w-12 shrink-0 text-right text-[10px] text-ink-faint">
          {ticks.map((tick) => (
            <span key={tick} className="absolute right-0 -translate-y-1/2 whitespace-nowrap" style={{ top: `${100 - y(tick)}%` }}>
              {axis(tick)}
            </span>
          ))}
        </div>
        <div className="relative h-48 flex-1">
          {ticks.map((tick) => (
            <div
              key={tick}
              className={cn("absolute inset-x-0 border-t", tick === 0 ? "border-ink-faint/60" : "border-dashed border-border")}
              style={{ top: `${100 - y(tick)}%` }}
            />
          ))}
          <div className={cn("absolute inset-0 flex", bars.length > 40 ? "gap-0" : "gap-px")}>
            {bars.map((bar, i) => {
              const top = y(Math.max(bar.value, 0));
              const bottom = y(Math.min(bar.value, 0));
              // A small day still shows: at least a sliver, never nothing.
              const height = bar.value === 0 ? 0 : Math.max(top - bottom, 1.5);
              const negative = bar.value < 0;
              return (
                <div
                  key={bar.key}
                  data-bar
                  tabIndex={0}
                  aria-label={`${bar.title}: ${format(bar.value)}`}
                  className="group relative h-full min-w-0 flex-1 rounded-sm outline-none hover:bg-primary/5 focus:bg-primary/5"
                >
                  {bar.value === 0 ? (
                    <div className="absolute left-1/2 h-0.5 w-3/5 max-w-10 -translate-x-1/2 rounded-full bg-border" style={{ bottom: `calc(${zero}% - 1px)` }} />
                  ) : (
                    <div
                      className={cn(
                        "absolute left-1/2 w-4/5 max-w-14 -translate-x-1/2",
                        negative ? `rounded-b-sm ${styles.barDown}` : `rounded-t-md ${styles.barUp}`,
                      )}
                      style={
                        {
                          ...(negative ? { top: `${100 - zero}%` } : { bottom: `${zero}%` }),
                          height: `${height}%`,
                          "--bar": negative ? "var(--color-danger)" : color,
                          "--i": i,
                        } as unknown as CSSProperties
                      }
                    />
                  )}
                  {withValues && bar.value !== 0 ? (
                    <span
                      className={cn(
                        "absolute inset-x-0 text-center text-[10px] font-medium whitespace-nowrap text-ink-muted",
                        styles.fadeIn,
                        !valuesWhenNarrow && "hidden @md:block",
                      )}
                      style={
                        {
                          ...(negative ? { top: `calc(${100 - bottom}% + 2px)` } : { bottom: `calc(${top}% + 2px)` }),
                          "--i": i,
                        } as unknown as CSSProperties
                      }
                    >
                      {axis(bar.value)}
                    </span>
                  ) : null}
                  <div
                    role="tooltip"
                    className={cn(
                      "pointer-events-none absolute z-20 hidden max-w-[calc(100vw-3rem)] min-w-28 rounded-md bg-ink px-2.5 py-1.5 text-xs whitespace-nowrap text-white shadow-lg group-hover:block group-focus:block",
                      i < edge ? "left-0" : i >= bars.length - edge ? "right-0" : "left-1/2 -translate-x-1/2",
                    )}
                    style={{ bottom: `calc(${Math.min(top, 60)}% + ${withValues && valuesWhenNarrow ? 18 : 6}px)` }}
                  >
                    <p className="text-white/70">{bar.title}</p>
                    <p className="mt-0.5 text-sm font-semibold">{format(bar.value)}</p>
                    {bar.details?.map((line) => (
                      <p key={line} className="text-white/70">
                        {line}
                      </p>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
      <div className="ml-14 mt-1.5 flex text-[10px] text-ink-faint" aria-hidden="true">
        {bars.map((bar, i) => {
          const last = i === bars.length - 1;
          // Every few bars and the last one, but not two captions on top of each other.
          const shown = last || (i % labelEvery === 0 && bars.length - 1 - i >= labelEvery / 2);
          // A phone's card fits half the captions: every other one, and none
          // so close to the last that the two run into each other.
          const thin =
            bars.length <= 12
              ? bars.length > 7 && (bars.length - 1 - i) % 2 === 1
              : !last && (i % (2 * labelEvery) !== 0 || bars.length - 1 - i < 1.5 * labelEvery);
          return (
            <div key={bar.key} className="relative flex-1">
              {shown ? (
                <span
                  className={cn(
                    "absolute top-0 whitespace-nowrap",
                    thin && "hidden @md:block",
                    bars.length <= 12 ? "left-1/2 -translate-x-1/2" : last ? "right-0" : i === 0 ? "left-0" : "left-1/2 -translate-x-1/2",
                  )}
                >
                  {bar.label}
                </span>
              ) : null}
            </div>
          );
        })}
      </div>
      <div className="h-3" />
    </div>
  );
}

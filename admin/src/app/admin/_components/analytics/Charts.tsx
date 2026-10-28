import type { CSSProperties, ReactNode } from "react";
import { formatNumber } from "../tables/shared";
import { cn } from "@/ui";
import { compact } from "./BarChart";
import styles from "./charts.module.css";

/**
 * Charts beside BarChart: a donut for shares (channels, statuses, a
 * column's values), a ring for one share of a whole (completed orders,
 * profit margin), horizontal bars for a ranking. SVG and CSS only, so they
 * render on the server and animate on load.
 */

/** Colours for slices in order: the admin's own, then a few more that sit well beside them. */
export const PALETTE = ["#0f4c5c", "#527589", "#b98232", "#8a6bb0", "#c46a6a", "#5f9e8a", "#d39a52", "#6b8bb3", "#9a8a6e"];

/** Colours that mean something, by a value's key (lower case). */
const NAMED: Record<string, string> = {
  instagram: "#d1477a",
  facebook: "#3b69c9",
  tiktok: "#2b2f33",
  telegram: "#2a9fd6",
  whatsapp: "#2fa65a",
  viber: "#7a5bc4",
  կայք: "#0f4c5c",
  female: "#c4618c",
  male: "#4f7fa8",
  other: "#9aa39f",
};

export function colorFor(key: string, index: number): string {
  return NAMED[key.toLowerCase()] ?? PALETTE[index % PALETTE.length];
}

export type Slice = {
  key: string;
  label: string;
  value: number;
  /** A second line in the legend: "12 500 դր.". */
  note?: string;
  color?: string;
};

/** More than this many values: the smallest go into one «Այլ» slice. */
const MAX_SLICES = 6;

function fold(slices: Slice[]): Slice[] {
  const sorted = slices.filter((s) => s.value > 0);
  if (sorted.length <= MAX_SLICES) return sorted;
  const rest = sorted.slice(MAX_SLICES - 1);
  return [
    ...sorted.slice(0, MAX_SLICES - 1),
    { key: "__rest", label: `Այլ (${rest.length})`, value: rest.reduce((sum, s) => sum + s.value, 0), color: "#b9c0bc" },
  ];
}

function share(value: number, total: number): string {
  if (total <= 0) return "0%";
  const percent = (value / total) * 100;
  return `${formatNumber(percent >= 10 || percent === 0 ? Math.round(percent) : Math.round(percent * 10) / 10)}%`;
}

/**
 * Shares as a donut that draws itself slice by slice, the total in the
 * middle and a legend with each share. Hovering a slice dims the others.
 */
export function DonutChart({
  slices,
  total: totalLabel,
  caption,
  format = formatNumber,
  size = 168,
}: {
  slices: Slice[];
  /** In the middle, under the sum: "պատվեր". */
  total?: string;
  caption?: string;
  format?: (n: number) => string;
  size?: number;
}) {
  const shown = fold(slices).map((s, i) => ({ ...s, color: s.color ?? colorFor(s.key, i) }));
  const total = shown.reduce((sum, s) => sum + s.value, 0);

  if (total === 0) {
    return <p className="py-10 text-center text-sm text-ink-muted">Այս ժամանակահատվածում տվյալներ դեռ չկան։</p>;
  }

  // A small gap between slices, unless there is only one.
  const gap = shown.length > 1 ? 0.8 : 0;
  // Where each slice starts on the ring, in percent.
  const starts = shown.map((_, i) => shown.slice(0, i).reduce((sum, s) => sum + (s.value / total) * 100, 0));

  return (
    <div className="flex flex-col items-center gap-5 sm:flex-row sm:items-center">
      <div className="relative shrink-0" style={{ width: size, height: size }}>
        <svg viewBox="0 0 42 42" className={`${styles.donut} ${styles.spin} h-full w-full -rotate-90`} role="img" aria-label={caption}>
          <circle cx="21" cy="21" r="15.9155" fill="none" stroke="var(--color-surface-secondary)" strokeWidth="9" />
          {shown.map((s, i) => {
            const length = (s.value / total) * 100;
            const offset = starts[i];
            return (
              <circle
                key={s.key}
                cx="21"
                cy="21"
                r="15.9155"
                fill="none"
                stroke={s.color}
                strokeWidth="9"
                pathLength={100}
                strokeDasharray={`${Math.max(length - gap, 0.4)} ${100 - Math.max(length - gap, 0.4)}`}
                strokeDashoffset={-offset}
                className={styles.slice}
                style={{ "--i": i } as CSSProperties}
              >
                <title>{`${s.label}: ${format(s.value)} (${share(s.value, total)})`}</title>
              </circle>
            );
          })}
        </svg>
        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
          <span className="text-xl font-semibold leading-tight text-ink">{compactTotal(total, format)}</span>
          {totalLabel ? <span className="text-[11px] text-ink-muted">{totalLabel}</span> : null}
        </div>
      </div>

      <ul className="w-full min-w-0 flex-1 space-y-1">
        {shown.map((s, i) => (
          <li
            key={s.key}
            className={`${styles.legendItem} ${styles.fadeIn} flex items-center gap-2.5 rounded-md px-2 py-1.5`}
            style={{ "--i": i * 3 } as CSSProperties}
          >
            <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ background: s.color }} />
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm text-ink">{s.label}</span>
              {s.note ? <span className="block text-xs text-ink-faint">{s.note}</span> : null}
            </span>
            <span className="text-right text-sm tabular-nums">
              <span className="font-semibold text-ink">{share(s.value, total)}</span>
              <span className="block text-xs text-ink-muted">{format(s.value)}</span>
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function compactTotal(total: number, format: (n: number) => string): string {
  // Under the ring's width: "1 114 100 դր." reads as "1,1 մլն".
  const text = format(total);
  return text.length > 8 ? compact(total) : text;
}

/** One share of a whole as a ring that fills on load: "78%" of orders completed. */
export function RingStat({
  value,
  of,
  label,
  hint,
  color = "var(--color-primary)",
  size = 112,
  display,
}: {
  value: number;
  of: number;
  label: string;
  /** In the ring instead of the percent of the whole: "5,8%". */
  display?: string;
  hint?: ReactNode;
  color?: string;
  size?: number;
}) {
  const part = of > 0 ? Math.min(Math.max(value / of, 0), 1) : 0;
  const percent = Math.round(part * 100);

  return (
    <div className="flex items-center gap-4">
      <div className="relative shrink-0" style={{ width: size, height: size }}>
        <svg viewBox="0 0 42 42" className="h-full w-full -rotate-90" aria-hidden="true">
          <circle cx="21" cy="21" r="17" fill="none" stroke="var(--color-surface-secondary)" strokeWidth="5" />
          <circle
            cx="21"
            cy="21"
            r="17"
            fill="none"
            stroke={color}
            strokeWidth="5"
            pathLength={100}
            strokeDasharray="100"
            strokeDashoffset={100 - percent}
            className={styles.ring}
          />
        </svg>
        <div className="absolute inset-0 flex items-center justify-center">
          <span className={cn("font-semibold text-ink", size < 80 ? "text-sm" : size < 100 ? "text-base" : "text-xl")}>{display ?? `${percent}%`}</span>
        </div>
      </div>
      <div className="min-w-0">
        <p className="text-sm font-medium text-ink">{label}</p>
        {hint ? <p className="mt-0.5 text-xs text-ink-muted">{hint}</p> : null}
      </div>
    </div>
  );
}

export type HBar = { key: string; label: string; value: number; note?: string; color?: string };

/** A ranking: one bar per line, longest first, sliding out one after another. */
export function HBars({ bars, format = formatNumber, color }: { bars: HBar[]; format?: (n: number) => string; color?: string }) {
  const max = Math.max(...bars.map((b) => b.value), 0);
  if (max === 0) return <p className="py-6 text-center text-sm text-ink-muted">Այս ժամանակահատվածում տվյալներ դեռ չկան։</p>;

  return (
    <ul className="space-y-3">
      {bars.map((bar, i) => (
        <li key={bar.key} className="space-y-1">
          <div className="flex items-baseline justify-between gap-3 text-sm">
            <span className="min-w-0 truncate text-ink">{bar.label}</span>
            <span className="shrink-0 tabular-nums font-medium text-ink">
              {format(bar.value)}
              {bar.note ? <span className="ml-1.5 text-xs font-normal text-ink-faint">{bar.note}</span> : null}
            </span>
          </div>
          <div className="h-2.5 overflow-hidden rounded-full bg-surface-secondary">
            <div
              className={`${styles.hbar} h-full rounded-full`}
              style={
                {
                  width: `${Math.max((bar.value / max) * 100, bar.value > 0 ? 2 : 0)}%`,
                  "--bar": bar.color ?? color ?? colorFor(bar.key, i),
                  "--i": i,
                } as CSSProperties
              }
            />
          </div>
        </li>
      ))}
    </ul>
  );
}

/**
 * A chart drawn with sample figures, dimmed and marked «Օրինակ», for a
 * block whose data doesn't exist yet: the owner sees what it will show.
 */
export function SampleFrame({ children, badge = "Օրինակ" }: { children: ReactNode; badge?: string }) {
  return (
    <div className={styles.sample}>
      <span className={cn(styles.sampleBadge, "rounded-full bg-warning-light px-2 py-0.5 text-[11px] font-medium text-warning")}>
        {badge}
      </span>
      <div className={styles.sampleBody} aria-hidden="true">
        {children}
      </div>
    </div>
  );
}

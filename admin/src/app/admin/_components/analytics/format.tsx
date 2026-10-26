import Link from "next/link";
import type { AnalyticsPeriod, TableMetricDto } from "@/lib/adminApi";
import { cn } from "@/ui";
import { formatNumber } from "../tables/shared";

export const PERIODS: { days: AnalyticsPeriod; label: string }[] = [
  { days: 7, label: "7 օր" },
  { days: 30, label: "30 օր" },
  { days: 90, label: "90 օր" },
  { days: 365, label: "1 տարի" },
];

/** ?period=90 → 90; anything else → 30 days. */
export function periodFrom(value: string | string[] | undefined): AnalyticsPeriod {
  const requested = Number(Array.isArray(value) ? value[0] : value);
  return PERIODS.find((p) => p.days === requested)?.days ?? 30;
}

/** Whole drams: tenths of a dram mean nothing to a shop. */
export function money(value: number | null): string {
  return value === null ? "—" : `${formatNumber(Math.round(value))} դր.`;
}

export function formatMetric(metric: TableMetricDto, value: number | null): string {
  if (value === null) return "—";
  switch (metric.format) {
    case "money":
      return money(value);
    case "percent":
      return `${formatNumber(value)}%`;
    default:
      return formatNumber(value);
  }
}

export function PeriodTabs({ base, period }: { base: string; period: AnalyticsPeriod }) {
  return (
    <div className="segmented inline-flex rounded-md border border-border bg-surface p-1">
      {PERIODS.map((p) => (
        <Link
          key={p.days}
          href={`${base}?period=${p.days}`}
          className={cn(
            "rounded px-3 py-1.5 text-sm font-medium",
            p.days === period ? "bg-primary text-primary-foreground" : "text-ink-muted hover:text-ink",
          )}
        >
          {p.label}
        </Link>
      ))}
    </div>
  );
}

export const SOURCE_LABELS: Record<string, string> = {
  website: "Կայք",
  manual: "Ձեռքով",
  unknown: "Անհայտ",
};

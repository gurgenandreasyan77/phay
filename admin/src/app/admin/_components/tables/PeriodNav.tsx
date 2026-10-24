// The period a page shows (?period=&date=): all time, or one day, week,
// month, quarter or year, stepped with arrows. Links only, so it works in
// server components.
import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import type { PeriodKind } from "@/lib/adminApi";
import { buttonClasses, cn } from "@/ui";
import { PERIOD_KINDS, PERIOD_UNIT_LABELS, periodOf, shiftPeriod, today } from "./periods";

/** The period of the page's search params; month of today by default, null for all time. */
export function periodFromParams(params: Record<string, string | string[] | undefined>): { kind: PeriodKind; date: string } | null {
  const kind = typeof params.period === "string" ? params.period : "month";
  const date = typeof params.date === "string" && /^\d{4}-\d{2}-\d{2}$/.test(params.date) ? params.date : today();
  if (kind === "all") return null;
  return PERIOD_KINDS.includes(kind as PeriodKind) ? { kind: kind as PeriodKind, date } : { kind: "month", date };
}

export function PeriodNav({ base, current }: { base: string; current: { kind: PeriodKind; date: string } | null }) {
  const href = (kind: PeriodKind | "all", date?: string) =>
    kind === "all" ? `${base}?period=all` : `${base}?period=${kind}&date=${date}`;
  const period = current ? periodOf(current.kind, current.date) : null;
  const from = period?.start ?? today();

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="flex flex-wrap gap-1 rounded-md border border-border bg-surface p-1">
        {PERIOD_KINDS.map((kind) => (
          <Link
            key={kind}
            href={href(kind, periodOf(kind, from).start)}
            className={cn(
              "rounded px-3 py-1.5 text-sm font-medium",
              current?.kind === kind ? "bg-primary text-primary-foreground" : "text-ink-muted hover:text-ink",
            )}
          >
            {PERIOD_UNIT_LABELS[kind]}
          </Link>
        ))}
        <Link
          href={href("all")}
          className={cn("rounded px-3 py-1.5 text-sm font-medium", current === null ? "bg-primary text-primary-foreground" : "text-ink-muted hover:text-ink")}
        >
          Ամբողջը
        </Link>
      </div>
      {period ? (
        <div className="flex items-center gap-1">
          <Link href={href(period.kind, shiftPeriod(period.kind, period.start, -1).start)} className={buttonClasses("ghost", "sm")} aria-label="Նախորդ">
            <ChevronLeft className="h-4 w-4" />
          </Link>
          <span className="whitespace-nowrap text-sm font-medium text-ink">{period.label}</span>
          <Link href={href(period.kind, shiftPeriod(period.kind, period.start, 1).start)} className={buttonClasses("ghost", "sm")} aria-label="Հաջորդ">
            <ChevronRight className="h-4 w-4" />
          </Link>
        </div>
      ) : null}
    </div>
  );
}

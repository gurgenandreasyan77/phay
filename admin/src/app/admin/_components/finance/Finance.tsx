// Net profit of the business per day, week, month, quarter or year: what
// came in, the cost of goods, the expenses and what was left (see the API's
// Finance). Server components: the period is in the page's search params
// (?by=&at=), stepped with links.
import Link from "next/link";
import { ChevronLeft, ChevronRight, Info } from "lucide-react";
import type { FinanceCategoryDto, FinanceDto, FinancePeriodDto, PeriodKind } from "@/lib/adminApi";
import { buttonClasses, Card, cn, TableWrap, Td, Th, Thead, Tr } from "../ui";
import { money as formatMoney } from "../analytics/format";
import { PERIOD_KINDS, PERIOD_UNIT_LABELS, shiftPeriod, today } from "../tables/periods";
import { BarChart } from "../analytics/BarChart";

/** The view's kind and day from ?by=&at=; months up to today by default. */
export function financeParams(params: Record<string, string | string[] | undefined>): { by: PeriodKind; date: string } {
  const by = typeof params.by === "string" && PERIOD_KINDS.includes(params.by as PeriodKind) ? (params.by as PeriodKind) : "month";
  const date = typeof params.at === "string" && /^\d{4}-\d{2}-\d{2}$/.test(params.at) ? params.at : today();
  return { by, date };
}

/** Kind tabs and arrows to the period before or after; `keep` are other params of the page. */
export function FinanceNav({ base, report, keep = {} }: { base: string; report: FinanceDto; keep?: Record<string, string> }) {
  const href = (by: PeriodKind, at?: string) => `${base}?${new URLSearchParams({ ...keep, by, ...(at ? { at } : {}) })}`;
  const current = report.current;
  const next = shiftPeriod(current.kind, current.start, 1).start;

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="flex flex-wrap gap-1 rounded-md border border-border bg-surface p-1">
        {PERIOD_KINDS.map((kind) => (
          <Link
            key={kind}
            href={href(kind)}
            className={cn("rounded px-3 py-1.5 text-sm font-medium", report.by === kind ? "bg-primary text-primary-foreground" : "text-ink-muted hover:text-ink")}
          >
            {PERIOD_UNIT_LABELS[kind]}
          </Link>
        ))}
      </div>
      <div className="flex items-center gap-1">
        <Link href={href(current.kind, shiftPeriod(current.kind, current.start, -1).start)} className={buttonClasses("ghost", "sm")} aria-label="Նախորդ">
          <ChevronLeft className="h-4 w-4" />
        </Link>
        <span className="whitespace-nowrap text-sm font-medium text-ink">{current.label}</span>
        {next <= report.today ? (
          <Link href={href(current.kind, next)} className={buttonClasses("ghost", "sm")} aria-label="Հաջորդ">
            <ChevronRight className="h-4 w-4" />
          </Link>
        ) : null}
      </div>
    </div>
  );
}

function change(value: number, previous: number | undefined): string | null {
  if (previous === undefined || previous === value) return null;
  const diff = value - previous;
  return `${diff > 0 ? "+" : "−"}${formatMoney(Math.abs(diff))} նախորդի համեմատ`;
}

function Tile({ label, value, hint, previous, tone }: { label: string; value: number; hint?: string; previous?: number; tone?: "strong" | "muted" }) {
  const delta = change(value, previous);
  return (
    <Card className={cn("space-y-1", tone === "strong" && "border-primary")}>
      <p className="text-xs text-ink-muted">{label}</p>
      <p className={cn("text-2xl font-semibold tabular-nums", value < 0 ? "text-danger" : "text-ink", tone === "muted" && "text-ink-muted")}>
        {formatMoney(value)}
      </p>
      {hint ? <p className="text-xs text-ink-muted">{hint}</p> : null}
      {delta ? <p className="text-xs text-ink-faint">{delta}</p> : null}
    </Card>
  );
}

/** The figures of one period: from revenue down to the net profit and the cash left. */
export function FinanceTiles({ period, previous, withCost }: { period: FinancePeriodDto; previous?: FinancePeriodDto | null; withCost: boolean }) {
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <Tile label="Եկամուտ" value={period.revenue} previous={previous?.revenue} />
      {withCost ? <Tile label="Ապրանքի ինքնարժեք" value={period.cogs} hint={`Համախառն շահույթ՝ ${formatMoney(period.grossProfit)}`} previous={previous?.cogs} /> : null}
      <Tile label="Ծախսեր (շահույթում հաշվվող)" value={period.expenses} previous={previous?.expenses} />
      <Tile
        label="Զուտ շահույթ"
        value={period.netProfit}
        hint={period.margin !== null ? `Շահութաբերություն՝ ${String(period.margin).replace(".", ",")}%` : undefined}
        previous={previous?.netProfit}
        tone="strong"
      />
      {period.excluded > 0 ? <Tile label="Շահույթից դուրս ծախսեր" value={period.excluded} hint="Սարքավորում, վարկ, սեփականատիրոջ վերցրածը…" tone="muted" /> : null}
      <Tile label="Մնացած գումար" value={period.cashLeft} hint="Զուտ շահույթ − շահույթից դուրս ծախսեր" previous={previous?.cashLeft} />
    </div>
  );
}

/** Where the money went: expenses by kind, the biggest first. */
export function FinanceCategories({ categories }: { categories: FinanceCategoryDto[] }) {
  const spent = categories.filter((c) => c.amount > 0);
  if (spent.length === 0) return <p className="text-sm text-ink-muted">Այս ժամանակահատվածում ծախսեր չկան։</p>;
  const max = Math.max(...spent.map((c) => c.amount));
  const total = spent.reduce((sum, c) => sum + c.amount, 0);

  return (
    <ul className="space-y-2.5">
      {spent.map((c) => (
        <li key={`${c.id ?? ""}-${c.name}`} className="space-y-1">
          <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
            <span className={cn("text-ink", !c.inProfit && "text-ink-muted")}>
              {c.name}
              {!c.inProfit ? <span className="ml-1 text-xs">(շահույթից դուրս)</span> : null}
              {c.id === null ? <span className="ml-1 text-xs text-warning">(ցանկում չկա)</span> : null}
            </span>
            <span className="tabular-nums text-ink">
              {formatMoney(c.amount)} <span className="text-xs text-ink-faint">· {Math.round((c.amount / total) * 100)}%</span>
            </span>
          </div>
          <div className="h-1.5 rounded-full bg-surface-secondary">
            <div className={cn("h-full rounded-full", c.inProfit ? "bg-primary/70" : "bg-ink-faint/50")} style={{ width: `${(c.amount / max) * 100}%` }} />
          </div>
          {c.recurring > 0 ? <p className="text-xs text-ink-faint">որից կրկնվող՝ {formatMoney(c.recurring)}</p> : null}
        </li>
      ))}
    </ul>
  );
}

export function FinanceNotes({ notes }: { notes: string[] }) {
  if (notes.length === 0) return null;
  return (
    <div className="space-y-1.5 rounded-md border border-border bg-surface-secondary p-3 text-sm text-ink-muted">
      {notes.map((note) => (
        <p key={note} className="flex gap-2">
          <Info className="mt-0.5 h-4 w-4 shrink-0" />
          {note}
        </p>
      ))}
    </div>
  );
}

/**
 * The periods of the view from the first one with any money in or out:
 * a business that started in March has no empty January and February.
 */
export function activePeriods(report: FinanceDto): FinancePeriodDto[] {
  const first = report.periods.findIndex((p) => p.revenue !== 0 || p.cogs !== 0 || p.expenses !== 0 || p.excluded !== 0);
  return first === -1 ? report.periods.slice(-1) : report.periods.slice(first);
}

/** Under a bar: "17", "Հոկ", "W42", "Q4 '26", "2026". */
function barLabel(p: FinancePeriodDto): string {
  switch (p.kind) {
    case "day":
      return p.label.split(" ")[0];
    case "month":
      return p.label.split(" ")[0].slice(0, 3);
    case "quarter":
      return `Q${p.key.slice(-1)} '${p.key.slice(2, 4)}`;
    default:
      return p.key.replace(/^\d{4}-/, "");
  }
}

/** Net profit of each period of the view, as bars (losses below zero). */
export function NetProfitChart({ report }: { report: FinanceDto }) {
  return (
    <BarChart
      bars={activePeriods(report).map((p) => ({
        key: p.key,
        label: barLabel(p),
        title: p.label,
        value: p.netProfit,
        details: [`Եկամուտ՝ ${formatMoney(p.revenue)}`, `Ծախսեր՝ ${formatMoney(p.expenses + p.cogs)}`],
      }))}
      format={formatMoney}
    />
  );
}

/** Every period of the view in exact numbers, the latest first, with the total. */
export function FinanceTable({ report }: { report: FinanceDto }) {
  const withCost = report.has.cost;
  const withExcluded = report.total.excluded > 0;
  const row = (p: Omit<FinancePeriodDto, "kind" | "key" | "start" | "end" | "label">, label: string, strong = false) => (
    <>
      <Td className={cn("whitespace-nowrap", strong && "font-semibold text-ink")}>{label}</Td>
      <Td className="text-right tabular-nums">{formatMoney(p.revenue)}</Td>
      {withCost ? <Td className="text-right tabular-nums">{formatMoney(p.cogs)}</Td> : null}
      <Td className="text-right tabular-nums">{formatMoney(p.expenses)}</Td>
      <Td className={cn("text-right font-medium tabular-nums", p.netProfit < 0 ? "text-danger" : "text-ink")}>{formatMoney(p.netProfit)}</Td>
      <Td className="text-right tabular-nums text-ink-muted">{p.margin !== null ? `${String(p.margin).replace(".", ",")}%` : "—"}</Td>
      {withExcluded ? <Td className="text-right tabular-nums text-ink-muted">{formatMoney(p.excluded)}</Td> : null}
      <Td className="text-right tabular-nums">{formatMoney(p.cashLeft)}</Td>
    </>
  );

  return (
    <TableWrap>
      <Thead>
        <Th>Ժամանակահատված</Th>
        <Th className="text-right">Եկամուտ</Th>
        {withCost ? <Th className="text-right">Ինքնարժեք</Th> : null}
        <Th className="text-right">Ծախսեր</Th>
        <Th className="text-right">Զուտ շահույթ</Th>
        <Th className="text-right">%</Th>
        {withExcluded ? <Th className="text-right">Շահույթից դուրս</Th> : null}
        <Th className="text-right">Մնացած գումար</Th>
      </Thead>
      <tbody>
        {[...activePeriods(report)].reverse().map((p) => (
          <Tr key={p.key}>{row(p, p.label)}</Tr>
        ))}
        <Tr className="bg-surface-secondary">{row(report.total, "Ընդամենը", true)}</Tr>
      </tbody>
    </TableWrap>
  );
}

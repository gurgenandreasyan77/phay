import Link from "next/link";
import { CalendarSearch, ChevronLeft, ChevronRight } from "lucide-react";
import { requirePermission } from "@/lib/adminAuth";
import { getExpenseStatistics, type ExpenseLineDto, type ExpenseStatisticsDto, type PeriodKind } from "@/lib/adminApi";
import { Badge, Button, buttonClasses, Card, cn, EmptyCard, Input, PageHeader, SectionCard, TableWrap, Td, Th, Thead, Tr } from "../../_components/ui";
import { BarChart } from "../../_components/analytics/BarChart";
import { DonutChart, HBars } from "../../_components/analytics/Charts";
import { money } from "../../_components/analytics/format";
import { FinanceCategories } from "../../_components/finance/Finance";
import { formatMoment, tablePath } from "../../_components/tables/shared";
import { PERIOD_KINDS, PERIOD_UNIT_LABELS, shiftPeriod, today } from "../../_components/tables/periods";
import { ExpensesTabs } from "../ExpensesTabs";

export const dynamic = "force-dynamic";

/** Spending reads in a warm red, apart from the greens of sales. */
const SPENT = "var(--color-chart-5)";

function params(search: Record<string, string | string[] | undefined>): { by: PeriodKind; date: string } {
  const by = typeof search.by === "string" && PERIOD_KINDS.includes(search.by as PeriodKind) ? (search.by as PeriodKind) : "month";
  const date = typeof search.at === "string" && /^\d{4}-\d{2}-\d{2}$/.test(search.at) ? search.at : today();
  return { by, date };
}

function href(by: PeriodKind, at?: string): string {
  return `/admin/expenses/statistics?${new URLSearchParams({ by, ...(at ? { at } : {}) })}`;
}

/** "+12 500 դր. (+8%)" against the period before. */
function change(value: number, previous: number): { text: string; up: boolean } | null {
  if (previous === value) return null;
  const diff = value - previous;
  const percent = previous > 0 ? ` (${diff > 0 ? "+" : "−"}${Math.round(Math.abs(diff / previous) * 100)}%)` : "";
  return { text: `${diff > 0 ? "+" : "−"}${money(Math.abs(diff))}${percent}`, up: diff > 0 };
}

/**
 * What the business spent, on its own: pick a day, week, month, quarter or
 * year (or any date), see how much in all and against the period before,
 * by kind, by whom it was paid to, how and by whom, the biggest expenses
 * and every one of them. Sales and profit stay on their own screens.
 */
export default async function ExpenseStatisticsPage(props: PageProps<"/admin/expenses/statistics">) {
  await requirePermission("expenses");
  const { by, date } = params(await props.searchParams);
  const report = await getExpenseStatistics({ by, date });

  return (
    <div className="space-y-6">
      <PageHeader
        title="Ծախսերի վիճակագրություն"
        description="Որքան է ծախսվել ընտրված օրը, շաբաթը, ամիսը, եռամսյակը կամ տարին՝ ըստ տեսակների, ստացողների, վճարման եղանակի և աշխատակիցների։ Վաճառքներն ու շահույթը այստեղ չեն խառնվում։"
      />
      <ExpensesTabs active="statistics" />

      {!report ? (
        <EmptyCard>Չհաջողվեց բեռնել վիճակագրությունը։ Թարմացրեք էջը։</EmptyCard>
      ) : (
        <Report report={report} chosen={date} />
      )}
    </div>
  );
}

function Report({ report, chosen }: { report: ExpenseStatisticsDto; chosen: string }) {
  const { period, totals } = report;
  const next = shiftPeriod(period.kind, period.start, 1).start;
  const delta = change(totals.total, totals.previous);
  // The date asked for stays in the field; arrows land on the period's first day.
  const picked = period.start <= chosen && chosen <= period.end ? chosen : period.start;

  return (
    <>
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex flex-wrap gap-1 rounded-md border border-border bg-surface p-1">
          {PERIOD_KINDS.map((kind) => (
            <Link
              key={kind}
              href={href(kind, period.start)}
              className={cn(
                "rounded px-3 py-1.5 text-sm font-medium",
                report.by === kind ? "bg-primary text-primary-foreground" : "text-ink-muted hover:text-ink",
              )}
            >
              {PERIOD_UNIT_LABELS[kind]}
            </Link>
          ))}
        </div>
        <div className="flex items-center gap-1">
          <Link href={href(report.by, shiftPeriod(period.kind, period.start, -1).start)} className={buttonClasses("ghost", "sm")} aria-label="Նախորդ">
            <ChevronLeft className="h-4 w-4" />
          </Link>
          <span className="whitespace-nowrap text-sm font-semibold text-ink first-letter:uppercase">{period.label}</span>
          {next <= report.today ? (
            <Link href={href(report.by, next)} className={buttonClasses("ghost", "sm")} aria-label="Հաջորդ">
              <ChevronRight className="h-4 w-4" />
            </Link>
          ) : null}
        </div>
        {/* Any date: the period of the chosen kind it falls in. */}
        <form action="/admin/expenses/statistics" className="flex items-center gap-2 sm:ml-auto">
          <input type="hidden" name="by" value={report.by} />
          <Input type="date" name="at" defaultValue={picked} max={report.today} className="w-40" aria-label="Ամսաթիվ" />
          <Button type="submit" variant="secondary" size="sm" className="whitespace-nowrap">
            <CalendarSearch className="h-4 w-4" />
            Ցույց տալ
          </Button>
        </form>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Card className="space-y-1 border-chart-5/50">
          <p className="text-xs text-ink-muted">Ընդամենը ծախսեր</p>
          <p className="text-2xl font-semibold tabular-nums text-ink">{money(totals.total)}</p>
          {delta ? (
            <p className={cn("text-xs", delta.up ? "text-danger" : "text-success")}>
              {delta.text} · {report.previous.label}
            </p>
          ) : (
            <p className="text-xs text-ink-faint">{report.previous.label}՝ {money(totals.previous)}</p>
          )}
          {report.unfinished ? <p className="text-xs text-ink-faint">Ժամանակահատվածը դեռ չի ավարտվել։</p> : null}
        </Card>
        <Card className="space-y-1">
          <p className="text-xs text-ink-muted">Ծախսերի քանակ</p>
          <p className="text-2xl font-semibold tabular-nums text-ink">{totals.count}</p>
          {totals.perDay !== null && report.by !== "day" ? <p className="text-xs text-ink-muted">Միջինը օրական՝ {money(totals.perDay)}</p> : null}
        </Card>
        <Card className="space-y-1">
          <p className="text-xs text-ink-muted">Կրկնվող / միանվագ</p>
          <p className="text-lg font-semibold tabular-nums text-ink">
            {money(totals.recurring)} <span className="text-ink-faint">/</span> {money(totals.oneOff)}
          </p>
          <p className="text-xs text-ink-muted">Վարձ, աշխատավարձ… և առանձին գնումներ</p>
        </Card>
        <Card className="space-y-1">
          <p className="text-xs text-ink-muted">Շահույթում հաշվվող</p>
          <p className="text-lg font-semibold tabular-nums text-ink">{money(totals.inProfit)}</p>
          {totals.excluded > 0 ? <p className="text-xs text-ink-muted">Շահույթից դուրս՝ {money(totals.excluded)}</p> : null}
          {totals.tax !== null ? <p className="text-xs text-ink-muted">Որից հարկ՝ {money(totals.tax)}</p> : null}
        </Card>
      </div>

      {totals.count === 0 ? (
        <EmptyCard>
          Այս ժամանակահատվածում ծախսեր չկան։{" "}
          <Link href="/admin/expenses" className="font-medium text-primary underline">
            Ավելացնել ծախսեր
          </Link>{" "}
          աղյուսակում կամ որպես կրկնվող ծախս։
        </EmptyCard>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-2">
        <SectionCard title={`Ծախսեր ըստ ${PERIOD_PLURAL[report.by]}`} description="Ընտրվածը՝ վերջինը">
          <Card>
            <BarChart
              color={SPENT}
              format={money}
              bars={report.series.map((p) => ({
                key: p.key,
                label: shortLabel(p.kind, p.start, p.label),
                title: p.label,
                value: p.total,
                details: [`${p.count} ծախս`, ...(p.recurring > 0 ? [`կրկնվող՝ ${money(p.recurring)}`] : [])],
              }))}
            />
          </Card>
        </SectionCard>
        {report.inside.length > 0 ? (
          <SectionCard title={report.by === "week" || report.by === "month" ? "Ըստ օրերի" : "Ըստ ամիսների"} description={period.label}>
            <Card>
              <BarChart
                color={SPENT}
                format={money}
                bars={report.inside.map((p) => ({
                  key: p.key,
                  label: shortLabel(report.by === "week" || report.by === "month" ? "day" : "month", p.start, p.label),
                  title: p.label,
                  value: p.total,
                  details: [`${p.count} ծախս`],
                }))}
              />
            </Card>
          </SectionCard>
        ) : null}
      </div>

      {report.categories.length > 0 ? (
        <SectionCard title="Ըստ ծախսի տեսակների" description="Որտեղ է գնացել գումարը">
          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <DonutChart
                format={money}
                total="ծախս"
                caption="Ծախսեր ըստ տեսակների"
                slices={report.categories.map((c) => ({
                  key: c.name,
                  label: c.name,
                  value: c.amount,
                  note: previousNote(c.amount, report.previousCategories[c.name]),
                }))}
              />
            </Card>
            <Card>
              <FinanceCategories categories={report.categories} />
            </Card>
          </div>
        </SectionCard>
      ) : null}

      {report.breakdowns.length > 0 ? (
        <div className="grid gap-6 lg:grid-cols-2">
          {report.breakdowns.map((b) => (
            <SectionCard key={b.role} title={`Ըստ «${b.label}»`} description={b.distinct > b.values.length ? `Առաջին ${b.values.length}-ը ${b.distinct}-ից` : undefined}>
              <Card>
                <HBars
                  format={money}
                  color={SPENT}
                  bars={b.values.map((v) => ({ key: v.value, label: v.value, value: v.amount, note: `${v.count} ծախս` }))}
                />
              </Card>
            </SectionCard>
          ))}
        </div>
      ) : totals.count > 0 && report.has.tables ? (
        <p className="rounded-md border border-border bg-surface-secondary px-4 py-3 text-sm text-ink-muted">
          Ավելացրեք ծախսերի աղյուսակում «Ում է վճարվել», «Վճարման եղանակ», «Ով է ծախսել» կամ «Օբյեկտ» սյունակներ, և այստեղ
          կտեսնեք ծախսերը նաև ըստ դրանց։
        </p>
      ) : null}

      {report.largest.length > 0 ? (
        <SectionCard title="Ամենամեծ ծախսերը">
          <ExpensesTable rows={report.largest} />
        </SectionCard>
      ) : null}

      {report.rows.length > 0 ? (
        <SectionCard
          title={`Բոլոր ծախսերը՝ ${period.label}`}
          description={report.count > report.rows.length ? `Ցույց են տրված վերջին ${report.rows.length}-ը ${report.count}-ից։` : `${report.count} ծախս`}
        >
          <ExpensesTable rows={report.rows} />
        </SectionCard>
      ) : null}
    </>
  );
}

const PERIOD_PLURAL: Record<PeriodKind, string> = {
  day: "օրերի",
  week: "շաբաթների",
  month: "ամիսների",
  quarter: "եռամսյակների",
  year: "տարիների",
};

const MONTHS = ["հնվ", "փտվ", "մրտ", "ապր", "մյս", "հնս", "հլս", "օգս", "սեպ", "հոկ", "նոյ", "դեկ"];

/** Under a bar: "17 հոկ", "հոկ", "4-րդ ’26", "2026". */
function shortLabel(kind: PeriodKind, start: string, label: string): string {
  const [y, m, d] = start.split("-").map(Number);
  switch (kind) {
    case "day":
    case "week":
      return `${d} ${MONTHS[m - 1]}`;
    case "month":
      return MONTHS[m - 1];
    case "quarter": {
      const q = Math.ceil(m / 3);
      return `${q}-${q === 1 ? "ին" : "րդ"} ’${String(y).slice(2)}`;
    }
    default:
      return label;
  }
}

function previousNote(amount: number, previous: number | undefined): string | undefined {
  if (previous === undefined) return "նախորդում չկար";
  const delta = change(amount, previous);
  return delta ? `${delta.text} նախորդի համեմատ` : "նույնը, ինչ նախորդում";
}

function ExpensesTable({ rows }: { rows: ExpenseLineDto[] }) {
  const has = (field: keyof ExpenseLineDto) => rows.some((r) => r[field] !== null && r[field] !== "");

  return (
    <TableWrap>
      <Thead>
        <Th>Ամսաթիվ</Th>
        <Th>Տեսակ</Th>
        <Th>Ինչի համար</Th>
        {has("supplier") ? <Th>Ում</Th> : null}
        {has("payment") ? <Th>Վճարում</Th> : null}
        {has("performer") ? <Th>Ով</Th> : null}
        {has("project") ? <Th>Օբյեկտ</Th> : null}
        <Th className="text-right">Գումար</Th>
      </Thead>
      <tbody>
        {rows.map((r, i) => (
          <Tr key={`${r.rowId ?? "r"}-${r.date}-${i}`}>
            <Td className="whitespace-nowrap">{formatMoment(r.date)}</Td>
            <Td>
              {r.category}
              {r.recurring ? <Badge className="ml-1.5">կրկնվող</Badge> : null}
              {!r.inProfit ? <span className="ml-1 text-xs text-ink-faint">(շահույթից դուրս)</span> : null}
            </Td>
            <Td>
              {r.item ?? "—"}
              {r.tableId && r.table ? (
                <Link href={tablePath("expenses", r.tableId)} className="block text-xs text-ink-faint hover:text-ink">
                  {r.table}
                </Link>
              ) : null}
            </Td>
            {has("supplier") ? <Td>{r.supplier ?? "—"}</Td> : null}
            {has("payment") ? <Td>{r.payment ?? "—"}</Td> : null}
            {has("performer") ? <Td>{r.performer ?? "—"}</Td> : null}
            {has("project") ? <Td>{r.project ?? "—"}</Td> : null}
            <Td className="whitespace-nowrap text-right font-medium tabular-nums">{money(r.amount)}</Td>
          </Tr>
        ))}
      </tbody>
    </TableWrap>
  );
}

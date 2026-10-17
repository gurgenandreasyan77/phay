import Link from "next/link";
import { Sparkles } from "lucide-react";
import { requirePermission } from "@/lib/adminAuth";
import {
  getAnalytics,
  getFeatureFlagsAdmin,
  getTableAnalytics,
  type AnalyticsPeriod,
  type AnalyticsTotalsDto,
  type TableAnalyticsDto,
} from "@/lib/adminApi";
import { formatMoment, formatNumber, tablePath, tablesPath } from "../_components/tables/shared";
import { LEAD_STATUS_LABELS, LEAD_STATUSES } from "../_components/leadStatus";
import {
  buttonClasses,
  Card,
  cn,
  EmptyCard,
  EmptyRow,
  PageHeader,
  SectionCard,
  TableWrap,
  Td,
  Th,
  Thead,
  Tr,
} from "../_components/ui";

export const dynamic = "force-dynamic";

const PERIODS: { days: AnalyticsPeriod; label: string }[] = [
  { days: 7, label: "7 օր" },
  { days: 30, label: "30 օր" },
  { days: 90, label: "90 օր" },
  { days: 365, label: "1 տարի" },
];

const SOURCE_LABELS: Record<string, string> = {
  website: "Կայք",
  manual: "Ձեռքով",
  unknown: "Անհայտ",
};

const MONTHS = ["հնվ", "փտվ", "մրտ", "ապր", "մյս", "հնս", "հլս", "օգս", "սեպ", "հոկ", "նոյ", "դեկ"];

function money(value: number | null): string {
  return value === null ? "—" : `${new Intl.NumberFormat("hy-AM").format(value)} դր.`;
}

function bucketLabel(date: string): string {
  const [, month, day] = date.split("-");
  const monthName = MONTHS[Number(month) - 1] ?? month;
  return day ? `${Number(day)} ${monthName}` : monthName;
}

/** "+12%" against the previous period of the same length. */
function Delta({ current, previous }: { current: number | null; previous: number | null }) {
  if (current === null || previous === null) return null;
  if (previous === 0) {
    return current > 0 ? <p className="mt-1 text-xs text-success">նախորդում՝ 0</p> : null;
  }
  const change = Math.round(((current - previous) / previous) * 100);
  return (
    <p className={cn("mt-1 text-xs", change > 0 ? "text-success" : change < 0 ? "text-danger" : "text-ink-muted")}>
      {change > 0 ? "+" : ""}
      {change}% նախորդ ժամանակահատվածի համեմատ
    </p>
  );
}

function Kpi({
  label,
  value,
  current,
  previous,
  highlight,
}: {
  label: string;
  value: string;
  current: number | null;
  previous: number | null;
  highlight?: boolean;
}) {
  return (
    <Card className={highlight ? "border-primary-light bg-primary-light" : undefined}>
      <p className={cn("text-sm", highlight ? "text-primary/80" : "text-ink-muted")}>{label}</p>
      <p className={cn("mt-1 text-2xl font-semibold", highlight ? "text-primary" : "text-ink")}>{value}</p>
      <Delta current={current} previous={previous} />
    </Card>
  );
}

function BarChart({
  points,
  value,
  format,
}: {
  points: { date: string }[];
  value: (index: number) => number;
  format: (n: number) => string;
}) {
  const values = points.map((_, i) => value(i));
  const max = Math.max(...values, 0);
  const labelEvery = Math.ceil(points.length / 8);

  if (max === 0) {
    return <p className="py-10 text-center text-sm text-ink-muted">Այս ժամանակահատվածում տվյալներ դեռ չկան։</p>;
  }

  return (
    <div>
      <div className="flex h-40 items-end gap-px">
        {points.map((point, i) => (
          <div
            key={point.date}
            className="group relative flex h-full flex-1 items-end"
            title={`${bucketLabel(point.date)}: ${format(values[i])}`}
          >
            <div
              className="w-full rounded-t-sm bg-primary/70 transition-colors group-hover:bg-primary"
              style={{ height: `${values[i] === 0 ? 0 : Math.max((values[i] / max) * 100, 2)}%` }}
            />
          </div>
        ))}
      </div>
      <div className="mt-2 flex gap-px text-[10px] text-ink-faint">
        {points.map((point, i) => (
          <div key={point.date} className="flex-1 overflow-visible whitespace-nowrap">
            {i % labelEvery === 0 ? bucketLabel(point.date) : ""}
          </div>
        ))}
      </div>
    </div>
  );
}

function first(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

export default async function AdminAnalyticsPage(props: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  await requirePermission("insights");
  const searchParams = await props.searchParams;
  const requested = Number(first(searchParams.period));
  const period = (PERIODS.find((p) => p.days === requested)?.days ?? 30) as AnalyticsPeriod;
  const [analytics, flags, tables] = await Promise.all([
    getAnalytics(period),
    getFeatureFlagsAdmin(),
    getTableAnalytics(period),
  ]);
  // A shop without the orders module has no site orders to show.
  const ordersOn = flags.find((f) => f.key === "orders")?.active ?? true;
  const orderTables = tables.find((t) => t.section === "orders");

  return (
    <div className="space-y-8">
      <PageHeader
        title="Վերլուծություն"
        description={
          ordersOn
            ? "Ձեր պատվերների, եկամտի և ձեր աղյուսակների ամփոփումը։ Կայքի պատվերների եկամուտը հաշվվում է կատարված պատվերների վերջնական գնից, աղյուսակներինը՝ յուրաքանչյուր տողի ամսաթվով և գումարով։"
            : "Ձեր բոլոր աղյուսակների ամփոփումը ընտրված ժամանակահատվածի համար՝ ըստ յուրաքանչյուր տողի ամսաթվի։"
        }
        actions={
          <Link href="/admin/assistant" className={buttonClasses("secondary")}>
            <Sparkles className="h-4 w-4" />
            Հարցնել AI օգնականին
          </Link>
        }
      />

      <div className="inline-flex rounded-md border border-border bg-surface p-1">
        {PERIODS.map((p) => (
          <Link
            key={p.days}
            href={`/admin/analytics?period=${p.days}`}
            className={cn(
              "rounded px-3 py-1.5 text-sm font-medium",
              p.days === period ? "bg-primary text-primary-foreground" : "text-ink-muted hover:text-ink",
            )}
          >
            {p.label}
          </Link>
        ))}
      </div>

      {orderTables?.withSite ? <AllOrders summary={orderTables} period={period} /> : null}

      {!ordersOn ? null : analytics ? (
        <>
          {orderTables ? <h2 className="border-t border-border pt-8 text-lg font-semibold text-ink">Կայքի պատվերներ</h2> : null}
          <Totals totals={analytics.totals} previous={analytics.previous} />

          {analytics.totals.completedWithoutPrice > 0 ? (
            <p className="rounded-md border border-warning/30 bg-warning-light px-4 py-3 text-sm text-warning">
              {analytics.totals.completedWithoutPrice} կատարված պատվեր չունի վերջնական գին։ Լրացրեք այն{" "}
              <Link href="/admin/orders?status=completed" className="font-medium underline">
                Պատվերներ
              </Link>{" "}
              բաժնում, որպեսզի եկամուտը ճիշտ հաշվվի։
            </p>
          ) : null}

          <div className="grid gap-6 lg:grid-cols-2">
            <SectionCard title={period > 90 ? "Պատվերներ ըստ ամիսների" : "Պատվերներ ըստ օրերի"}>
              <Card>
                <BarChart
                  points={analytics.series}
                  value={(i) => analytics.series[i].orders}
                  format={(n) => `${n} պատվեր`}
                />
              </Card>
            </SectionCard>
            <SectionCard title={period > 90 ? "Եկամուտ ըստ ամիսների" : "Եկամուտ ըստ օրերի"}>
              <Card>
                <BarChart points={analytics.series} value={(i) => analytics.series[i].revenue} format={money} />
              </Card>
            </SectionCard>
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <SectionCard title="Լավագույն ծառայություններ">
              <TableWrap>
                <Thead>
                  <Th>Ծառայություն</Th>
                  <Th className="text-right">Պատվերներ</Th>
                  <Th className="text-right">Եկամուտ</Th>
                </Thead>
                <tbody>
                  {analytics.topServices.map((row) => (
                    <Tr key={row.serviceId ?? "none"}>
                      <Td className="font-medium">{row.name ?? "Առանց ծառայության"}</Td>
                      <Td className="text-right">{row.orders}</Td>
                      <Td className="whitespace-nowrap text-right">{money(row.revenue)}</Td>
                    </Tr>
                  ))}
                  {analytics.topServices.length === 0 ? <EmptyRow colSpan={3}>Պատվերներ դեռ չկան։</EmptyRow> : null}
                </tbody>
              </TableWrap>
            </SectionCard>

            <SectionCard title="Որտեղից են գալիս պատվերները">
              <TableWrap>
                <Thead>
                  <Th>Աղբյուր</Th>
                  <Th className="text-right">Պատվերներ</Th>
                  <Th className="text-right">Կատարված</Th>
                </Thead>
                <tbody>
                  {analytics.sources.map((row) => (
                    <Tr key={row.source}>
                      <Td className="font-medium">{SOURCE_LABELS[row.source] ?? row.source}</Td>
                      <Td className="text-right">{row.orders}</Td>
                      <Td className="text-right">{row.completed}</Td>
                    </Tr>
                  ))}
                  {analytics.sources.length === 0 ? <EmptyRow colSpan={3}>Պատվերներ դեռ չկան։</EmptyRow> : null}
                </tbody>
              </TableWrap>
            </SectionCard>
          </div>

          <SectionCard
            title="Պատվերներն ըստ կարգավիճակի"
            description={`Այս ժամանակահատվածի պատվերները։ Հիմա բաց են ${analytics.openOrders} պատվեր։`}
          >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
              {LEAD_STATUSES.map((status) => (
                <Card key={status} className="p-4">
                  <p className="text-xs text-ink-muted">{LEAD_STATUS_LABELS[status]}</p>
                  <p className="mt-1 text-xl font-semibold text-ink">{analytics.statuses[status] ?? 0}</p>
                </Card>
              ))}
            </div>
          </SectionCard>
        </>
      ) : (
        <p className="text-sm text-danger">Չհաջողվեց բեռնել տվյալները։</p>
      )}

      {tables.map((summary) => (
        <SectionFigures key={summary.section} summary={summary} />
      ))}

      {!ordersOn && tables.length === 0 ? (
        <EmptyCard>
          Աղյուսակներ դեռ չկան։ Ստեղծեք դրանք «Վաճառքներ» բաժնում և լրացրեք ձեռքով կամ ֆայլից, այստեղ կհայտնվեն
          վաճառքների թվերը։
        </EmptyCard>
      ) : null}
    </div>
  );
}

/** Orders of the order tables and of the site together: how many and how much. */
function AllOrders({ summary, period }: { summary: TableAnalyticsDto; period: AnalyticsPeriod }) {
  const all = summary.withSite!;
  const byMonth = period > 90;

  return (
    <section className="space-y-4">
      <div>
        <h2 className="text-lg font-semibold text-ink">Բոլոր պատվերները</h2>
        <p className="mt-1 max-w-2xl text-sm text-ink-muted">Կայքի պատվերները և ձեր աղյուսակների պատվերները միասին։</p>
      </div>
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Kpi label="Եկամուտ" value={money(all.revenue)} current={all.revenue} previous={all.previousRevenue} highlight />
        <Kpi label="Պատվերներ" value={String(all.orders)} current={all.orders} previous={all.previousOrders} />
      </div>
      <div className="grid gap-6 lg:grid-cols-2">
        <SectionCard title={byMonth ? "Պատվերներ ըստ ամիսների" : "Պատվերներ ըստ օրերի"}>
          <Card>
            <BarChart points={all.series} value={(i) => all.series[i].rows} format={(n) => `${n} պատվեր`} />
          </Card>
        </SectionCard>
        <SectionCard title={byMonth ? "Եկամուտ ըստ ամիսների" : "Եկամուտ ըստ օրերի"}>
          <Card>
            <BarChart points={all.series} value={(i) => all.series[i].amount} format={money} />
          </Card>
        </SectionCard>
      </div>
    </section>
  );
}

/**
 * All tables of a section together (thirty daily files read as one month):
 * rows and money in the period, by day/month, top values, and each table.
 */
function SectionFigures({ summary }: { summary: TableAnalyticsDto }) {
  const { section, rows, amount, series, breakdowns, period, tables } = summary;
  const byMonth = period.days > 90;
  const label = section === "orders" ? "Պատվերներ աղյուսակներից" : "Վաճառքներ";
  const unit = section === "orders" ? "պատվեր" : "վաճառք";

  return (
    <section className="space-y-4 border-t border-border pt-8">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold text-ink">{label}</h2>
          <p className="mt-1 max-w-2xl text-sm text-ink-muted">
            {tables.length === 1 ? "1 աղյուսակ" : `${tables.length} աղյուսակ միասին`}, ըստ յուրաքանչյուր տողի ամսաթվի։
          </p>
        </div>
        <Link href={tablesPath(section)} className={buttonClasses("ghost", "sm")}>
          Բոլոր աղյուսակները
        </Link>
      </div>

      {rows.withoutDate > 0 ? (
        <p className="rounded-md border border-warning/30 bg-warning-light px-4 py-3 text-sm text-warning">
          {rows.withoutDate} տող առանց ամսաթվի է և չի հաշվվում ոչ մի ժամանակահատվածում։ Լրացրեք ամսաթիվը աղյուսակում կամ
          ընտրեք ամսաթվի սյունակը աղյուսակի կարգավորումներում։
        </p>
      ) : null}

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {amount ? (
          <Kpi label="Գումար" value={money(amount.current)} current={amount.current} previous={amount.previous} highlight />
        ) : null}
        <Kpi label={section === "orders" ? "Պատվերներ" : "Վաճառքներ"} value={String(rows.current)} current={rows.current} previous={rows.previous} />
        {amount ? <Kpi label={`Միջինը մեկ ${unit}ում`} value={money(amount.average)} current={null} previous={null} /> : null}
        {summary.numbers.slice(0, amount ? 2 : 3).map((n) => (
          <Kpi
            key={`${n.label}|${n.unit ?? ""}`}
            label={`${n.label}${n.unit ? ` (${n.unit})` : ""}, ընդամենը`}
            value={formatNumber(n.sum)}
            current={null}
            previous={null}
          />
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        {amount ? (
          <SectionCard title={`Գումար ըստ ${byMonth ? "ամիսների" : "օրերի"}`}>
            <Card>
              <BarChart points={series} value={(i) => series[i].amount} format={money} />
            </Card>
          </SectionCard>
        ) : null}
        <SectionCard title={`${section === "orders" ? "Պատվերներ" : "Վաճառքներ"} ըստ ${byMonth ? "ամիսների" : "օրերի"}`}>
          <Card>
            <BarChart points={series} value={(i) => series[i].rows} format={(n) => `${n} ${unit}`} />
          </Card>
        </SectionCard>
      </div>

      {breakdowns.length > 0 ? (
        <div className="grid gap-6 lg:grid-cols-2">
          {breakdowns.slice(0, 4).map((breakdown) => (
            <SectionCard key={breakdown.label} title={`Ըստ «${breakdown.label}»`}>
              <TableWrap>
                <Thead>
                  <Th>{breakdown.label}</Th>
                  <Th className="text-right">Տողեր</Th>
                  {amount ? <Th className="text-right">Գումար</Th> : null}
                </Thead>
                <tbody>
                  {breakdown.values.map((row) => (
                    <Tr key={row.value}>
                      <Td className="font-medium">{row.value}</Td>
                      <Td className="text-right">{row.rows}</Td>
                      {amount ? <Td className="whitespace-nowrap text-right">{money(row.amount)}</Td> : null}
                    </Tr>
                  ))}
                </tbody>
              </TableWrap>
              {breakdown.distinct > breakdown.values.length ? (
                <p className="text-xs text-ink-muted">
                  Ցույց են տրված առաջին {breakdown.values.length}-ը {breakdown.distinct} տարբեր արժեքներից։
                </p>
              ) : null}
            </SectionCard>
          ))}
        </div>
      ) : null}

      <SectionCard title="Ըստ աղյուսակների">
        <TableWrap>
          <Thead>
            <Th>Աղյուսակ</Th>
            <Th className="text-right">Տողեր ժամանակահատվածում</Th>
            {amount ? <Th className="text-right">Գումար</Th> : null}
            <Th>Ամսաթվերը</Th>
          </Thead>
          <tbody>
            {tables.slice(0, 15).map((table) => (
              <Tr key={table.id}>
                <Td>
                  <Link href={tablePath(section, table.id)} className="font-medium text-ink hover:text-primary">
                    {table.name}
                  </Link>
                  {!table.dateColumn ? <span className="block text-xs text-warning">Ամսաթվի սյունակ ընտրված չէ</span> : null}
                </Td>
                <Td className="text-right">
                  {table.rows} <span className="text-ink-faint">/ {table.total}</span>
                </Td>
                {amount ? <Td className="whitespace-nowrap text-right">{money(table.amount)}</Td> : null}
                <Td className="whitespace-nowrap text-ink-muted">
                  {table.firstAt && table.lastAt
                    ? `${formatMoment(table.firstAt.slice(0, 10))} – ${formatMoment(table.lastAt.slice(0, 10))}`
                    : "—"}
                </Td>
              </Tr>
            ))}
          </tbody>
        </TableWrap>
        {tables.length > 15 ? (
          <p className="text-xs text-ink-muted">
            Ցույց են տրված առաջին 15-ը {tables.length}-ից։{" "}
            <Link href={tablesPath(section)} className="underline">
              Բոլոր աղյուսակները
            </Link>
          </p>
        ) : null}
      </SectionCard>
    </section>
  );
}

function Totals({ totals, previous }: { totals: AnalyticsTotalsDto; previous: AnalyticsTotalsDto }) {
  return (
    <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
      <Kpi
        label="Եկամուտ"
        value={money(totals.revenue)}
        current={totals.revenue}
        previous={previous.revenue}
        highlight
      />
      <Kpi label="Պատվերներ" value={String(totals.orders)} current={totals.orders} previous={previous.orders} />
      <Kpi label="Կատարված" value={String(totals.completed)} current={totals.completed} previous={previous.completed} />
      <Kpi
        label="Միջին չեկ"
        value={money(totals.averageCheck)}
        current={totals.averageCheck}
        previous={previous.averageCheck}
      />
      <Kpi
        label="Կատարման տոկոս"
        value={totals.conversion === null ? "—" : `${totals.conversion}%`}
        current={totals.conversion}
        previous={previous.conversion}
      />
    </div>
  );
}

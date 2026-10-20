import Link from "next/link";
import { Sparkles } from "lucide-react";
import { requirePermission } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import {
  getAnalytics,
  getFinance,
  type FinanceDto,
  getTableAnalytics,
  type AnalyticsDto,
  type AnalyticsPeriod,
  type AnalyticsTotalsDto,
  type TableAnalyticsDto,
  type TableMetricDto,
  type DashboardWidgetId,
} from "@/lib/adminApi";
import { formatMoment, formatNumber, SECTIONS, tablePath, tablesPath } from "../_components/tables/shared";
import { LEAD_STATUS_LABELS, LEAD_STATUSES } from "../_components/leadStatus";
import { WidgetPicker } from "../_components/analytics/WidgetPicker";
import { formatMetric, money, PeriodTabs, periodFrom, SOURCE_LABELS } from "../_components/analytics/format";
import { BarChart, bucketTitle, dateBars, weekdayShort, WEEKDAYS } from "../_components/analytics/BarChart";
import { FinanceCategories, FinanceNotes, FinanceTiles, NetProfitChart } from "../_components/finance/Finance";
import {
  buttonClasses,
  Card,
  cn,
  PageHeader,
  SectionCard,
  TableWrap,
  Td,
  Th,
  Thead,
  Tr,
} from "../_components/ui";

export const dynamic = "force-dynamic";

/** "+12%" against the previous period of the same length. */
function Delta({ current, previous }: { current: number | null; previous: number | null }) {
  if (current === null || previous === null) return null;
  // Nothing before (often: the business didn't record yet): no comparison.
  if (previous === 0) return null;
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
  note,
}: {
  label: string;
  value: string;
  current: number | null;
  previous: number | null;
  highlight?: boolean;
  note?: string | null;
}) {
  return (
    <Card className={highlight ? "border-primary-light bg-primary-light" : undefined}>
      <p className={cn("text-sm", highlight ? "text-primary/80" : "text-ink-muted")}>{label}</p>
      <p className={cn("mt-1 text-2xl font-semibold", highlight ? "text-primary" : "text-ink")}>{value}</p>
      <Delta current={current} previous={previous} />
      {note ? <p className="mt-1 text-xs text-ink-faint">{note}</p> : null}
    </Card>
  );
}

export default async function AdminAnalyticsPage(props: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const me = await requirePermission("insights");
  const searchParams = await props.searchParams;
  const period = periodFrom(searchParams.period);
  const [analytics, tables, finance] = await Promise.all([
    getAnalytics(period),
    getTableAnalytics(period),
    can(me, "expenses") ? getFinance({ by: "month" }) : null,
  ]);
  // Only what the business has: its site orders if it ever had one, and
  // each section whose tables have rows. Nothing yet: how to start.
  const site = analytics && analytics.everOrders > 0 ? analytics : null;
  const sections = tables.filter((t) => t.rows.total > 0);
  const orderTables = sections.find((t) => t.section === "orders");
  const empty = !site && sections.length === 0;

  return (
    <div className="space-y-8">
      <PageHeader
        title="Վերլուծություն"
        description="Կառուցվում է ձեր պատվերներից և վաճառքներից՝ միայն այն թվերը, որոնք կան ձեր աղյուսակներում։ Գին ունեցող աղյուսակը ցույց կտա եկամուտ և միջին չեկ, քանակով և միավորով աղյուսակը՝ կիլոգրամներ կամ հատեր, իսկ միայն ամսաթվով աղյուսակը՝ պատվերների քանակը օրերով և ամիսներով։"
        actions={
          empty ? null : (
            <Link href="/admin/assistant" className={buttonClasses("secondary")}>
              <Sparkles className="h-4 w-4" />
              Հարցնել AI օգնականին
            </Link>
          )
        }
      />

      {finance?.has.expenses ? <NetProfit report={finance} /> : null}

      {empty ? (
        <EmptyStart hasTables={tables.length > 0} failed={!analytics} />
      ) : (
        <>
          <PeriodTabs base="/admin/analytics" period={period} />

          {site && orderTables?.withSite ? <AllOrders summary={orderTables} period={period} /> : null}

          {site ? <SiteOrders analytics={site} period={period} withHeading={Boolean(orderTables)} /> : null}

          {sections.map((summary) => (
            <SectionFigures key={summary.section} summary={summary} besideSite={Boolean(site)} />
          ))}
        </>
      )}
    </div>
  );
}

/** Net profit month by month (revenue − cost of goods − expenses) and this month in short. */
function NetProfit({ report }: { report: FinanceDto }) {
  return (
    <SectionCard
      title="Զուտ շահույթ ըստ ամիսների"
      description="Եկամուտ − ապրանքի ինքնարժեք − ծախսեր։ Կարմիր սյունը վնասով ամիս է։"
      actions={
        <Link href="/admin/statistics?by=month#finance" className="text-sm font-medium text-primary hover:underline">
          Ճշգրիտ թվերը →
        </Link>
      }
    >
      <Card>
        <NetProfitChart report={report} />
      </Card>
      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <FinanceTiles period={report.current} previous={report.current.previous} withCost={report.has.cost} />
        <Card>
          <p className="mb-3 text-sm font-medium text-ink">Ծախսեր՝ {report.current.label}</p>
          <FinanceCategories categories={report.current.categories} />
        </Card>
      </div>
      <FinanceNotes notes={report.notes} />
    </SectionCard>
  );
}

/** No orders and no rows yet: analytics starts from the business's own tables. */
function EmptyStart({ hasTables, failed }: { hasTables: boolean; failed: boolean }) {
  const sections = ["orders", "sales"] as const;

  return (
    <Card className="space-y-4 py-10 text-center">
      <p className="text-lg font-semibold text-ink">Վերլուծությունը դեռ դատարկ է</p>
      <p className="mx-auto max-w-xl text-sm text-ink-muted">
        {hasTables
          ? "Ձեր աղյուսակներում դեռ տողեր չկան։ Ներմուծեք Excel կամ CSV ֆայլ կամ ավելացրեք տողեր ձեռքով, և այստեղ կհայտնվեն ձեր թվերը։"
          : "Այստեղ կհայտնվեն ձեր պատվերների և վաճառքների թվերը։ Սկսեք՝ ներմուծելով Excel կամ CSV ֆայլ կամ ստեղծելով աղյուսակ ձեռքով։"}{" "}
        Նշեք, թե որ սյունակն է ամսաթիվը, գինը, քանակը, և վերլուծությունը կհաշվի միայն ձեր բիզնեսին համապատասխան թվերը։
      </p>
      {failed ? <p className="text-sm text-danger">Չհաջողվեց բեռնել կայքի պատվերները։</p> : null}
      <div className="flex flex-wrap justify-center gap-3">
        {sections.map((section) => (
          <Link key={section} href={tablePath(section)} className={buttonClasses(section === sections[0] ? "primary" : "secondary")}>
            {section === "orders" ? "Ավելացնել պատվերներ" : "Ավելացնել վաճառքներ"}
          </Link>
        ))}
      </div>
    </Card>
  );
}

/** Orders from the site and the orders screen, for a business that has any. */
function SiteOrders({
  analytics,
  period,
  withHeading,
}: {
  analytics: AnalyticsDto;
  period: AnalyticsPeriod;
  withHeading: boolean;
}) {
  const statuses = LEAD_STATUSES.filter((status) => (analytics.statuses[status] ?? 0) > 0);

  return (
    <section className="space-y-6">
      {withHeading ? <h2 className="border-t border-border pt-8 text-lg font-semibold text-ink">Կայքի պատվերներ</h2> : null}
      <Totals totals={analytics.totals} previous={analytics.previous} />

      {analytics.totals.completedWithoutPrice > 0 ? (
        <p className="rounded-md border border-warning/30 bg-warning-light px-4 py-3 text-sm text-warning">
          {analytics.totals.completedWithoutPrice} կատարված պատվեր չունի վերջնական գին։ Լրացրեք այն{" "}
          <Link href="/admin/orders/site?status=completed" className="font-medium underline">
            Պատվերներ
          </Link>{" "}
          բաժնում, որպեսզի եկամուտը ճիշտ հաշվվի։
        </p>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-2">
        <SectionCard title={period > 90 ? "Պատվերներ ըստ ամիսների" : "Պատվերներ ըստ օրերի"}>
          <Card>
            <BarChart
              bars={dateBars(analytics.series, (p) => p.orders, (p) => (p.revenue ? [`Եկամուտ՝ ${money(p.revenue)}`] : []))}
              format={(n) => `${formatNumber(n)} պատվեր`}
            />
          </Card>
        </SectionCard>
        {analytics.totals.revenue > 0 || analytics.previous.revenue > 0 ? (
          <SectionCard title={period > 90 ? "Եկամուտ ըստ ամիսների" : "Եկամուտ ըստ օրերի"}>
            <Card>
              <BarChart
                bars={dateBars(analytics.series, (p) => p.revenue, (p) => [`${p.orders} պատվեր`])}
                format={money}
              />
            </Card>
          </SectionCard>
        ) : null}
      </div>

      {analytics.topServices.length > 0 || analytics.sources.length > 0 ? (
        <div className="grid gap-6 lg:grid-cols-2">
          {analytics.topServices.length > 0 ? (
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
                </tbody>
              </TableWrap>
            </SectionCard>
          ) : null}

          {analytics.sources.length > 0 ? (
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
                </tbody>
              </TableWrap>
            </SectionCard>
          ) : null}
        </div>
      ) : null}

      {statuses.length > 0 ? (
        <SectionCard
          title="Պատվերներն ըստ կարգավիճակի"
          description={`Այս ժամանակահատվածի պատվերները։ Հիմա բաց են ${analytics.openOrders} պատվեր։`}
        >
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            {statuses.map((status) => (
              <Card key={status} className="p-4">
                <p className="text-xs text-ink-muted">{LEAD_STATUS_LABELS[status]}</p>
                <p className="mt-1 text-xl font-semibold text-ink">{analytics.statuses[status]}</p>
              </Card>
            ))}
          </div>
        </SectionCard>
      ) : null}
    </section>
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
            <BarChart
              bars={dateBars(all.series, (p) => p.rows, (p) => (p.amount ? [`Եկամուտ՝ ${money(p.amount)}`] : []))}
              format={(n) => `${formatNumber(n)} պատվեր`}
            />
          </Card>
        </SectionCard>
        <SectionCard title={byMonth ? "Եկամուտ ըստ ամիսների" : "Եկամուտ ըստ օրերի"}>
          <Card>
            <BarChart bars={dateBars(all.series, (p) => p.amount, (p) => [`${p.rows} պատվեր`])} format={money} />
          </Card>
        </SectionCard>
      </div>
    </section>
  );
}

/**
 * All tables of a section together (thirty daily files read as one month),
 * as the owner set it up: only the widgets they chose, in catalog order,
 * and the catalog to add more from.
 */
function SectionFigures({ summary, besideSite }: { summary: TableAnalyticsDto; besideSite: boolean }) {
  const { section, rows, amount, breakdowns, period, tables, metrics, peaks, numbers, dashboard } = summary;
  const on = (id: DashboardWidgetId) => dashboard.enabled.includes(id);
  const byMonth = period.days > 90;
  // A year starts at the business's first month, not with empty months before it
  // (also when one month of data is too little for peaks).
  const started = summary.series.findIndex((p) => p.rows > 0);
  const series = byMonth && started > 0 ? summary.series.slice(started) : summary.series;
  // "From tables" only tells site orders and table orders apart.
  const label = section === "orders" ? (besideSite ? "Պատվերներ աղյուսակներից" : SECTIONS.orders.label) : SECTIONS.sales.label;
  const unit = section === "orders" ? "պատվեր" : "վաճառք";
  const over = byMonth ? "ամիսների" : "օրերի";
  const available = dashboard.widgets.filter((w) => w.missing.length === 0).length;

  const charts = [
    on("revenueChart") && amount ? (
      <SectionCard key="revenue" title={`Եկամուտ ըստ ${over}`}>
        <Card>
          <BarChart bars={dateBars(series, (p) => p.amount, (p) => [`${p.rows} ${unit}`])} format={money} />
        </Card>
      </SectionCard>
    ) : null,
    on("profitChart") && series.some((p) => p.profit !== undefined) ? (
      <SectionCard key="profit" title={`Շահույթ ըստ ${over}`}>
        <Card>
          <BarChart
            bars={dateBars(
              series,
              (p) => p.profit ?? 0,
              (p) => [`Եկամուտ՝ ${money(p.amount)}`, `${p.rows} ${unit}`],
            )}
            format={money}
          />
        </Card>
      </SectionCard>
    ) : null,
    on("countChart") ? (
      <SectionCard key="count" title={`${section === "orders" ? "Պատվերներ" : "Վաճառքներ"} ըստ ${over}`}>
        <Card>
          <BarChart
            bars={dateBars(series, (p) => p.rows, (p) => (amount ? [`Եկամուտ՝ ${money(p.amount)}`] : []))}
            format={(n) => `${formatNumber(n)} ${unit}`}
          />
        </Card>
      </SectionCard>
    ) : null,
    on("weekdays") && summary.weekdays.length > 0 ? (
      <SectionCard key="weekdays" title="Ըստ շաբաթվա օրերի">
        <Card>
          <BarChart
            bars={summary.weekdays.map((d) => ({
              key: String(d.day),
              label: weekdayShort(d.day),
              title: WEEKDAYS[d.day - 1],
              value: amount ? d.amount : d.rows,
              details: amount ? [`${d.rows} ${unit}`] : [],
            }))}
            format={(n) => (amount ? money(n) : `${formatNumber(n)} ${unit}`)}
          />
        </Card>
      </SectionCard>
    ) : null,
    on("hours") && summary.hours.length > 0 ? (
      <SectionCard key="hours" title="Ըստ ժամերի">
        <Card>
          <BarChart
            bars={summary.hours.map((h) => ({
              key: String(h.hour),
              label: `${h.hour}:00`,
              title: `${h.hour}:00 – ${h.hour + 1}:00`,
              value: h.rows,
            }))}
            format={(n) => `${formatNumber(n)} ${unit}`}
          />
        </Card>
      </SectionCard>
    ) : null,
  ].filter(Boolean);

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

      {!dashboard.saved ? (
        <p className="rounded-md border border-primary-light bg-primary-light px-4 py-3 text-sm text-primary">
          Ձեր տվյալների համար ընտրել ենք {dashboard.enabled.length} ցուցանիշ։ Հասանելի է ևս{" "}
          {Math.max(available - dashboard.enabled.length, 0)}-ը․ ավելացրեք կամ հանեք «Ավելացնել գրաֆիկ» կոճակով։
        </p>
      ) : null}

      {rows.withoutDate > 0 ? (
        <p className="rounded-md border border-warning/30 bg-warning-light px-4 py-3 text-sm text-warning">
          {rows.withoutDate} տող առանց ամսաթվի է և չի հաշվվում ոչ մի ժամանակահատվածում։ Լրացրեք ամսաթիվը աղյուսակում կամ
          նշեք ամսաթվի սյունակը աղյուսակի կարգավորումներում։
        </p>
      ) : null}

      {metrics.some((m) => !PER_UNIT.includes(m.key)) || numbers.length > 0 ? (
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          {metrics
            .filter((m) => !PER_UNIT.includes(m.key))
            .map((metric) => (
              <Kpi
                key={metric.key}
                label={metric.label}
                value={formatMetric(metric, metric.value)}
                current={metric.value}
                previous={metric.previous}
                highlight={metric.key === "revenue" || metric.key === "profit"}
                note={metric.note}
              />
            ))}
          {numbers.slice(0, 8).map((n) => (
            <Kpi key={`n|${n.label}`} label={`${n.label}, ընդամենը`} value={formatNumber(n.sum)} current={null} previous={null} />
          ))}
        </div>
      ) : null}

      <Units metrics={metrics} section={section} />

      {peaks ? <Peaks peaks={peaks} unit={unit} /> : null}

      {charts.length > 0 ? <div className="grid gap-6 lg:grid-cols-2">{charts}</div> : null}

      {summary.months?.peaks ? (
        <Months series={summary.months.series} peaks={summary.months.peaks} withAmount={amount !== null} unit={unit} />
      ) : null}

      {breakdowns.length > 0 ? (
        <div className="grid gap-6 lg:grid-cols-2">
          {breakdowns.map((breakdown) => (
            <SectionCard key={breakdown.label} title={`Ըստ «${breakdown.label}»`}>
              <TableWrap>
                <Thead>
                  <Th>{breakdown.label}</Th>
                  <Th className="text-right">{section === "orders" ? "Պատվերներ" : "Վաճառքներ"}</Th>
                  {amount ? <Th className="text-right">Եկամուտ</Th> : null}
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

      {on("tables") && tables.length > 1 ? (
        <SectionCard title="Ըստ աղյուսակների">
          <TableWrap>
            <Thead>
              <Th>Աղյուսակ</Th>
              <Th className="text-right">Տողեր ժամանակահատվածում</Th>
              {amount ? <Th className="text-right">Եկամուտ</Th> : null}
              <Th>Ամսաթվերը</Th>
            </Thead>
            <tbody>
              {tables.slice(0, 15).map((table) => (
                <Tr key={table.id}>
                  <Td>
                    <Link href={tablePath(section, table.id)} className="font-medium text-ink hover:text-primary">
                      {table.name}
                    </Link>
                    {!table.dateColumn ? <span className="block text-xs text-warning">Ամսաթվի սյունակ նշված չէ</span> : null}
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
        </SectionCard>
      ) : null}

      {dashboard.enabled.length === 0 ? (
        <p className="text-sm text-ink-muted">Ոչ մի ցուցանիշ ընտրված չէ։ Ավելացրեք նրանք, որոնք ձեզ պետք են։</p>
      ) : null}

      <WidgetPicker key={dashboard.enabled.join(",")} section={section} dashboard={dashboard} />
    </section>
  );
}

const PER_UNIT: TableMetricDto["key"][] = ["quantity", "averageQuantity", "unitPrice"];

/** Quantities per unit (kg, pieces...): sold, in one sale, the price of one. */
function Units({ metrics, section }: { metrics: TableMetricDto[]; section: TableAnalyticsDto["section"] }) {
  const units = [...new Set(metrics.filter((m) => m.key === "quantity").map((m) => m.unit ?? null))];
  if (units.length === 0) return null;
  const get = (key: TableMetricDto["key"], unit: string | null) => metrics.find((m) => m.key === key && (m.unit ?? null) === unit);
  const hasPrice = metrics.some((m) => m.key === "unitPrice");

  return (
    <TableWrap>
      <Thead>
        <Th>Միավոր</Th>
        <Th className="text-right">Ընդամենը</Th>
        <Th className="text-right">Միջինը մեկ {section === "orders" ? "պատվերում" : "վաճառքում"}</Th>
        {hasPrice ? <Th className="text-right">Մեկ միավորի միջին գինը</Th> : null}
      </Thead>
      <tbody>
        {units.map((unit) => {
          const quantity = get("quantity", unit)!;
          const average = get("averageQuantity", unit);
          const price = get("unitPrice", unit);
          return (
            <Tr key={unit ?? "none"}>
              <Td className="font-medium">{unit ?? "Քանակ"}</Td>
              <Td className="text-right">
                {formatMetric(quantity, quantity.value)}
                <Delta current={quantity.value} previous={quantity.previous} />
              </Td>
              <Td className="text-right">{average ? formatMetric(average, average.value) : "—"}</Td>
              {hasPrice ? <Td className="whitespace-nowrap text-right">{price ? money(price.value) : "—"}</Td> : null}
            </Tr>
          );
        })}
      </tbody>
    </TableWrap>
  );
}

type Peaks = NonNullable<TableAnalyticsDto["peaks"]>;

/** The best and the weakest day or month, against the average one. */
function Peaks({ peaks, unit }: { peaks: Peaks; unit: string }) {
  const month = peaks.bucket === "month";
  const value = (point: { rows: number; amount: number }) =>
    peaks.by === "amount" ? money(point.amount) : `${point[peaks.by]} ${unit}`;

  return (
    <div className="grid gap-4 sm:grid-cols-3">
      <Card className="p-4">
        <p className="text-xs text-ink-muted">{month ? "Լավագույն ամիսը" : "Լավագույն օրը"}</p>
        <p className="mt-1 font-semibold text-ink">{bucketTitle(peaks.best.date)}</p>
        <p className="text-sm text-success">{value(peaks.best)}</p>
      </Card>
      {peaks.worst ? (
        <Card className="p-4">
          <p className="text-xs text-ink-muted">{month ? "Ամենաթույլ ամիսը" : "Ամենաթույլ օրը"}</p>
          <p className="mt-1 font-semibold text-ink">{bucketTitle(peaks.worst.date)}</p>
          <p className="text-sm text-danger">{value(peaks.worst)}</p>
        </Card>
      ) : null}
      {peaks.average !== null ? (
        <Card className="p-4">
          <p className="text-xs text-ink-muted">{month ? "Միջին ամիսը" : "Միջին օրը"}</p>
          <p className="mt-1 font-semibold text-ink">
            {peaks.by === "amount" ? money(peaks.average) : `${formatNumber(peaks.average)} ${unit}`}
          </p>
          <p className="text-xs text-ink-faint">{month ? "ավարտված ամիսների" : "ավարտված օրերի"} միջինը</p>
        </Card>
      ) : null}
    </div>
  );
}

/** A year month by month: which months brought more, which less than the average one. */
function Months({
  series,
  peaks,
  withAmount,
  unit,
}: {
  series: TableAnalyticsDto["series"];
  peaks: Peaks;
  withAmount: boolean;
  unit: string;
}) {
  return (
    <SectionCard title="Տարվա ամիսները" description="Վերջին 12 ամիսը՝ ձեր առաջին տողի ամսից սկսած։ Ընթացիկ ամիսը դեռ ավարտված չէ։">
      <TableWrap>
        <Thead>
          <Th>Ամիս</Th>
          <Th className="text-right">{unit === "պատվեր" ? "Պատվերներ" : "Վաճառքներ"}</Th>
          {withAmount ? <Th className="text-right">Եկամուտ</Th> : null}
          <Th className="text-right">Միջինի համեմատ</Th>
        </Thead>
        <tbody>
          {[...series].reverse().map((point, index) => {
            const value = point[peaks.by];
            const change =
              index === 0 || !peaks.average ? null : Math.round(((value - peaks.average) / peaks.average) * 100);
            return (
              <Tr key={point.date}>
                <Td className="font-medium">
                  {bucketTitle(point.date)}
                  {point.date === peaks.best.date ? <span className="ml-2 text-xs text-success">լավագույն</span> : null}
                  {point.date === peaks.worst?.date ? <span className="ml-2 text-xs text-danger">ամենաթույլ</span> : null}
                  {index === 0 ? <span className="ml-2 text-xs text-ink-faint">ընթացիկ</span> : null}
                </Td>
                <Td className="text-right">{point.rows}</Td>
                {withAmount ? <Td className="whitespace-nowrap text-right">{money(point.amount)}</Td> : null}
                <Td
                  className={cn(
                    "text-right",
                    change === null ? "text-ink-faint" : change > 0 ? "text-success" : change < 0 ? "text-danger" : "text-ink-muted",
                  )}
                >
                  {change === null ? "—" : `${change > 0 ? "+" : ""}${change}%`}
                </Td>
              </Tr>
            );
          })}
        </tbody>
      </TableWrap>
    </SectionCard>
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

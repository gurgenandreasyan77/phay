import Link from "next/link";
import { TrendingUp } from "lucide-react";
import { requirePermission } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import {
  getAnalytics,
  getAudience,
  type AudienceDto,
  getFinance,
  type FinanceDto,
  getTableStatistics,
  type AnalyticsDto,
  type TableMetricDto,
  type TableStatisticsDto,
} from "@/lib/adminApi";
import { buttonClasses, Card, EmptyCard, EmptyRow, PageHeader, SectionCard, TableWrap, Td, Th, Thead, Tr, cn } from "@/ui";
import { formatMetric, money, PeriodTabs, periodFrom, SOURCE_LABELS } from "../_components/analytics/format";
import { BarChart, bucketTitle, dateBars } from "../_components/analytics/BarChart";
import { DonutChart, type Slice } from "../_components/analytics/Charts";
import { formatMoment, formatNumber, SECTIONS, tablePath } from "../_components/tables/shared";
import { FinanceCategories, FinanceNav, FinanceNotes, FinanceTable, FinanceTiles, financeParams, NetProfitChart } from "../_components/finance/Finance";

export const dynamic = "force-dynamic";

/** A day or a month of a section: how many, how much, and of those how many from the site. */
type Bucket = { date: string; rows: number; amount: number; profit?: number; site?: number };

/** A share of the period: "Խնձոր", 12 sales, 9 600 դր. */
type Share = { label: string; rows: number; amount: number | null };

type Stats = {
  key: string;
  title: string;
  unit: string;
  hasAmount: boolean;
  hasProfit: boolean;
  withSite: boolean;
  byMonth: boolean;
  from: string;
  to: string;
  buckets: Bucket[];
  metrics: TableMetricDto[];
  shares: { title: string; head: string; values: Share[] }[];
  numbers: TableStatisticsDto["numbers"];
  tables: TableStatisticsDto["tables"];
  section?: TableStatisticsDto["section"];
};

/**
 * Statistics: the exact numbers behind the analytics, as tables. Every day
 * or month of the period, every product or service, every table, with
 * totals, whatever the owner chose to see on the analytics dashboard.
 */
export default async function AdminStatisticsPage(props: PageProps<"/admin/statistics">) {
  const me = await requirePermission("insights");
  const searchParams = await props.searchParams;
  const period = periodFrom(searchParams.period);
  const [sections, site, finance, audience] = await Promise.all([
    getTableStatistics(period),
    getAnalytics(period),
    can(me, "expenses") ? getFinance(financeParams(searchParams)) : null,
    getAudience(period),
  ]);
  const siteOrders = site && site.everOrders > 0 ? site : null;

  const stats: Stats[] = sections.filter((s) => s.rows.total > 0).map((s) => fromTables(s, Boolean(siteOrders)));
  // Site orders and no order tables: the site's orders are the section.
  if (siteOrders && !stats.some((s) => s.section === "orders")) stats.unshift(fromSite(siteOrders, period));

  return (
    <div className="space-y-8">
      <PageHeader
        title="Վիճակագրություն"
        description="Ճշգրիտ թվերը ձեր աղյուսակներից՝ ամեն օրը կամ ամիսը, ամեն ապրանքը կամ ծառայությունը և ամեն աղյուսակը՝ ընդհանուր գումարներով։ Գրաֆիկներն ու լավագույն և թույլ ժամանակահատվածները «Վերլուծություն» բաժնում են։"
        actions={
          <Link href={`/admin/analytics?period=${period}`} className={buttonClasses("secondary")}>
            <TrendingUp className="h-4 w-4" />
            Վերլուծություն
          </Link>
        }
      />

      {finance ? <FinanceSection report={finance} period={String(period)} /> : null}

      {stats.length === 0 ? (
        <EmptyCard>
          Վիճակագրությունը դեռ դատարկ է։ Ավելացրեք տողեր{" "}
          <Link href={SECTIONS.orders.base} className="font-medium underline">
            Պատվերներ
          </Link>{" "}
          կամ{" "}
          <Link href={SECTIONS.sales.base} className="font-medium underline">
            Վաճառքներ
          </Link>{" "}
          բաժնի աղյուսակներում, և թվերը կհայտնվեն այստեղ։
        </EmptyCard>
      ) : (
        <>
          <PeriodTabs base="/admin/statistics" period={period} />
          {stats.map((s) => (
            <SectionStats key={s.key} stats={s} />
          ))}
          {audience ? <AudienceStats audience={audience} /> : null}
        </>
      )}
    </div>
  );
}

function fromTables(s: TableStatisticsDto, siteOrders: boolean): Stats {
  const byMonth = s.period.days > 90;
  const withSite = s.section === "orders" && siteOrders && Boolean(s.withSite);
  const own = new Map(s.series.map((p) => [p.date, p]));
  const buckets: Bucket[] = withSite
    ? s.withSite!.series.map((p) => ({
        date: p.date,
        rows: p.rows,
        amount: p.amount,
        profit: own.get(p.date)?.profit,
        site: p.rows - (own.get(p.date)?.rows ?? 0),
      }))
    : s.series;
  const unit = s.section === "orders" ? "Պատվերներ" : "Վաճառքներ";

  return {
    key: s.section,
    section: s.section,
    title: withSite ? "Պատվերներ՝ աղյուսակներից և կայքից" : SECTIONS[s.section].label,
    unit,
    hasAmount: s.amount !== null || withSite,
    hasProfit: s.series.some((p) => p.profit !== undefined),
    withSite,
    byMonth,
    from: s.period.from,
    to: s.period.to,
    buckets,
    metrics: s.metrics.filter((m) => !["perDay", "perMonth"].includes(m.key)),
    shares: s.breakdowns.map((b) => ({
      title: `Ըստ «${b.label}»`,
      head: b.label,
      values: b.values.map((v) => ({ label: v.value, rows: v.rows, amount: v.amount })),
    })),
    numbers: s.numbers,
    tables: s.tables,
  };
}

function fromSite(site: AnalyticsDto, period: number): Stats {
  const revenue = site.series.some((p) => p.revenue > 0);
  return {
    key: "site",
    title: "Կայքից եկած պատվերներ",
    unit: "Պատվերներ",
    hasAmount: revenue,
    hasProfit: false,
    withSite: false,
    byMonth: period > 90,
    from: site.series[0]?.date ?? "",
    to: site.series.at(-1)?.date ?? "",
    buckets: site.series.map((p) => ({ date: p.date, rows: p.orders, amount: p.revenue })),
    metrics: [],
    shares: [
      {
        title: "Ըստ ծառայությունների",
        head: "Ծառայություն",
        values: site.topServices.map((t) => ({ label: t.name ?? "Առանց ծառայության", rows: t.orders, amount: revenue ? t.revenue : null })),
      },
      {
        title: "Ըստ աղբյուրի",
        head: "Աղբյուր",
        values: site.sources.map((t) => ({ label: SOURCE_LABELS[t.source] ?? t.source, rows: t.orders, amount: null })),
      },
    ].filter((share) => share.values.length > 0),
    numbers: [],
    tables: [],
  };
}

/** "2026-10-18" → "18.10.2026"; a month "2026-10" → "10.2026". */
/** "Պատվերներ" → "պատվեր": what follows a number. */
function single(unit: string): string {
  return unit === "Վաճառքներ" ? "վաճառք" : unit === "Պատվերներ" ? "պատվեր" : unit.toLowerCase();
}

function day(date: string): string {
  return date.split("-").reverse().join(".");
}

function percent(part: number, whole: number): string {
  return whole > 0 ? `${formatNumber(Math.round((part / whole) * 1000) / 10)}%` : "—";
}

function SectionStats({ stats }: { stats: Stats }) {
  const { buckets, hasAmount, hasProfit, withSite, unit } = stats;
  // From the first day (or month) with rows: empty months before the business started say nothing.
  const started = buckets.findIndex((b) => b.rows > 0);
  const shown = (started > 0 ? buckets.slice(started) : buckets).slice().reverse();
  const rows = buckets.reduce((sum, b) => sum + b.rows, 0);
  const amount = buckets.reduce((sum, b) => sum + b.amount, 0);
  const profit = buckets.reduce((sum, b) => sum + (b.profit ?? 0), 0);
  const site = buckets.reduce((sum, b) => sum + (b.site ?? 0), 0);
  const active = buckets.filter((b) => b.rows > 0).length;

  return (
    <section className="space-y-6 border-t border-border pt-8 first-of-type:border-t-0 first-of-type:pt-0">
      <div>
        <h2 className="text-lg font-semibold text-ink">{stats.title}</h2>
        {stats.from ? (
          <p className="mt-1 text-sm text-ink-muted">
            {day(stats.from)} — {day(stats.to)}
          </p>
        ) : null}
      </div>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <Figure label={unit} value={formatNumber(rows)} />
        {hasAmount ? <Figure label="Եկամուտ" value={money(amount)} /> : null}
        {hasProfit ? <Figure label="Շահույթ" value={money(profit)} /> : null}
        {hasAmount && rows > 0 ? <Figure label="Միջին չեկ" value={money(amount / rows)} /> : null}
        <Figure
          label={stats.byMonth ? "Ամիսներ՝ տվյալներով" : "Օրեր՝ տվյալներով"}
          value={`${active} / ${shown.length}`}
        />
        {stats.metrics
          .filter((m) => ["discounts", "commission", "deliveryFees", "tax", "averageDuration", "totalDuration"].includes(m.key))
          .map((m) => (
            <Figure key={m.key} label={m.label} value={formatMetric(m, m.value)} />
          ))}
        {stats.metrics
          .filter((m) => m.unit !== undefined && m.key === "quantity")
          .map((m) => (
            <Figure key={`${m.key}-${m.unit}`} label={`Քանակ${m.unit ? ` (${m.unit})` : ""}`} value={formatMetric(m, m.value)} />
          ))}
      </div>

      <SectionCard title={stats.byMonth ? "Ըստ ամիսների" : "Ըստ օրերի"}>
        {active > 0 ? (
          <Card>
            <BarChart
              bars={dateBars(
                started > 0 ? buckets.slice(started) : buckets,
                (b) => (hasAmount ? b.amount : b.rows),
                (b) => (hasAmount ? [`${b.rows} ${single(unit)}`] : []),
              )}
              format={hasAmount ? money : (n) => `${formatNumber(n)} ${single(unit)}`}
            />
          </Card>
        ) : null}
        <TableWrap>
          <Thead>
            <Th>{stats.byMonth ? "Ամիս" : "Օր"}</Th>
            <Th className="text-right">{unit}</Th>
            {withSite ? <Th className="text-right">Որից կայքից</Th> : null}
            {hasAmount ? <Th className="text-right">Եկամուտ</Th> : null}
            {hasProfit ? <Th className="text-right">Շահույթ</Th> : null}
            {hasAmount ? <Th className="text-right">Միջին չեկ</Th> : null}
            {hasAmount ? <Th className="text-right">Մասնաբաժին</Th> : null}
          </Thead>
          <tbody>
            {shown.map((b) => (
              <Tr key={b.date} className={b.rows === 0 ? "text-ink-faint" : undefined}>
                <Td className={cn("whitespace-nowrap", b.rows > 0 && "font-medium text-ink")}>{bucketTitle(b.date)}</Td>
                <Td className="text-right">{b.rows}</Td>
                {withSite ? <Td className="text-right">{b.site ?? 0}</Td> : null}
                {hasAmount ? <Td className="whitespace-nowrap text-right">{b.rows > 0 ? money(b.amount) : "—"}</Td> : null}
                {hasProfit ? (
                  <Td className={cn("whitespace-nowrap text-right", (b.profit ?? 0) < 0 && "text-danger")}>
                    {b.profit !== undefined && b.rows > 0 ? money(b.profit) : "—"}
                  </Td>
                ) : null}
                {hasAmount ? <Td className="whitespace-nowrap text-right">{b.rows > 0 ? money(b.amount / b.rows) : "—"}</Td> : null}
                {hasAmount ? <Td className="text-right">{b.rows > 0 ? percent(b.amount, amount) : "—"}</Td> : null}
              </Tr>
            ))}
            {shown.length === 0 ? <EmptyRow colSpan={7}>Այս ժամանակահատվածում տվյալներ չկան։</EmptyRow> : null}
          </tbody>
          <tfoot>
            <TotalRow>
              <Td>Ընդամենը</Td>
              <Td className="text-right">{rows}</Td>
              {withSite ? <Td className="text-right">{site}</Td> : null}
              {hasAmount ? <Td className="whitespace-nowrap text-right">{money(amount)}</Td> : null}
              {hasProfit ? <Td className="whitespace-nowrap text-right">{money(profit)}</Td> : null}
              {hasAmount ? <Td className="whitespace-nowrap text-right">{rows > 0 ? money(amount / rows) : "—"}</Td> : null}
              {hasAmount ? <Td className="text-right">100%</Td> : null}
            </TotalRow>
          </tfoot>
        </TableWrap>
      </SectionCard>

      {stats.shares.length > 0 ? (
        <div className="grid gap-6 lg:grid-cols-2">
          {stats.shares.map((share) => (
            <ShareTable key={share.title} share={share} unit={unit} rows={rows} amount={amount} />
          ))}
        </div>
      ) : null}

      {stats.numbers.length > 0 ? (
        <SectionCard title="Ձեր թվային սյունակները">
          <TableWrap>
            <Thead>
              <Th>Սյունակ</Th>
              <Th className="text-right">Գումարը</Th>
              <Th className="text-right">Լրացված տողեր</Th>
            </Thead>
            <tbody>
              {stats.numbers.map((n) => (
                <Tr key={`${n.label}-${n.unit ?? ""}`}>
                  <Td className="font-medium">{n.label}</Td>
                  <Td className="text-right">
                    {formatNumber(n.sum)}
                    {n.unit ? ` ${n.unit}` : ""}
                  </Td>
                  <Td className="text-right">{n.filled}</Td>
                </Tr>
              ))}
            </tbody>
          </TableWrap>
        </SectionCard>
      ) : null}

      {stats.tables.length > 0 && stats.section ? (
        <SectionCard title="Ըստ աղյուսակների">
          <TableWrap>
            <Thead>
              <Th>Աղյուսակ</Th>
              <Th className="text-right">{unit} այս ժամանակահատվածում</Th>
              {hasAmount ? <Th className="text-right">Եկամուտ</Th> : null}
              <Th className="text-right">Բոլոր տողերը</Th>
              <Th>Վերջին տողը</Th>
            </Thead>
            <tbody>
              {stats.tables.map((t) => (
                <Tr key={t.id}>
                  <Td className="font-medium">
                    <Link href={tablePath(stats.section!, t.id)} className="hover:underline">
                      {t.name}
                    </Link>
                  </Td>
                  <Td className="text-right">{t.rows}</Td>
                  {hasAmount ? <Td className="whitespace-nowrap text-right">{t.amount === null ? "—" : money(t.amount)}</Td> : null}
                  <Td className="text-right">
                    {t.total}
                    {t.withoutDate > 0 ? <span className="block text-xs text-ink-faint">{t.withoutDate} առանց ամսաթվի</span> : null}
                  </Td>
                  <Td className="whitespace-nowrap text-ink-muted">{t.lastAt ? formatMoment(t.lastAt) : "—"}</Td>
                </Tr>
              ))}
            </tbody>
          </TableWrap>
        </SectionCard>
      ) : null}
    </section>
  );
}

function ShareTable({ share, unit, rows, amount }: { share: Stats["shares"][number]; unit: string; rows: number; amount: number }) {
  const withAmount = share.values.some((v) => v.amount !== null);
  return (
    <SectionCard title={share.title}>
      <Card>
        <DonutChart
          slices={share.values.map((v) => ({ key: v.label, label: v.label, value: withAmount ? (v.amount ?? 0) : v.rows }))}
          format={withAmount ? money : formatNumber}
          total={withAmount ? "դր." : single(unit)}
          caption={share.title}
          size={140}
        />
      </Card>
      <TableWrap>
        <Thead>
          <Th>{share.head}</Th>
          <Th className="text-right">{unit}</Th>
          <Th className="text-right">%</Th>
          {withAmount ? <Th className="text-right">Եկամուտ</Th> : null}
          {withAmount ? <Th className="text-right">%</Th> : null}
        </Thead>
        <tbody>
          {share.values.map((v) => (
            <Tr key={v.label}>
              <Td className="font-medium">{v.label}</Td>
              <Td className="text-right">{v.rows}</Td>
              <Td className="text-right text-ink-muted">{percent(v.rows, rows)}</Td>
              {withAmount ? <Td className="whitespace-nowrap text-right">{v.amount === null ? "—" : money(v.amount)}</Td> : null}
              {withAmount ? <Td className="text-right text-ink-muted">{v.amount === null ? "—" : percent(v.amount, amount)}</Td> : null}
            </Tr>
          ))}
        </tbody>
      </TableWrap>
    </SectionCard>
  );
}

const GENDER_LABELS: Record<string, string> = { female: "Իգական", male: "Արական", other: "Այլ" };
const AGE_LABELS: Record<string, string> = { "<18": "մինչև 18", "55+": "55 և ավելի" };

/** Channels, gender and age as exact numbers with shares; only what the tables have. */
function AudienceStats({ audience }: { audience: AudienceDto }) {
  const blocks = [
    audience.channels ? { title: "Ըստ աղբյուրի", head: "Աղբյուր", rows: audience.channels.map((c) => ({ ...c, name: c.label })) } : null,
    audience.gender ? { title: "Ըստ սեռի", head: "Սեռ", rows: audience.gender.map((g) => ({ ...g, name: GENDER_LABELS[g.key] ?? g.key })) } : null,
    audience.age ? { title: "Ըստ տարիքի", head: "Տարիք", rows: audience.age.map((g) => ({ ...g, name: AGE_LABELS[g.key] ?? g.key })) } : null,
  ].filter((b) => b !== null);
  if (blocks.length === 0) return null;

  return (
    <section className="space-y-6 border-t border-border pt-8">
      <h2 className="text-lg font-semibold text-ink">Հաճախորդներ</h2>
      <div className="grid gap-6 lg:grid-cols-2">
        {blocks.map((block) => {
          const total = block.rows.reduce((sum, r) => sum + r.rows, 0);
          const slices: Slice[] = block.rows.map((r) => ({ key: r.key, label: r.name, value: r.rows }));
          return (
            <SectionCard key={block.title} title={block.title}>
              <Card>
                <DonutChart slices={slices} total="գնում" caption={block.title} size={140} />
              </Card>
              <TableWrap>
                <Thead>
                  <Th>{block.head}</Th>
                  <Th className="text-right">Գնումներ</Th>
                  <Th className="text-right">%</Th>
                  <Th className="text-right">Եկամուտ</Th>
                </Thead>
                <tbody>
                  {block.rows.map((r) => (
                    <Tr key={r.key}>
                      <Td className="font-medium">{r.name}</Td>
                      <Td className="text-right">{r.rows}</Td>
                      <Td className="text-right text-ink-muted">{percent(r.rows, total)}</Td>
                      <Td className="whitespace-nowrap text-right">{r.amount === null ? "—" : money(r.amount)}</Td>
                    </Tr>
                  ))}
                </tbody>
              </TableWrap>
            </SectionCard>
          );
        })}
      </div>
    </section>
  );
}

function Figure({ label, value }: { label: string; value: string }) {
  return (
    <Card className="p-4">
      <p className="text-xs text-ink-muted">{label}</p>
      <p className="mt-1 text-xl font-semibold text-ink">{value}</p>
    </Card>
  );
}

function TotalRow({ children }: { children: React.ReactNode }) {
  return <tr className="border-t-2 border-border bg-surface-secondary font-semibold text-ink">{children}</tr>;
}

/**
 * Net profit per day, week, month, quarter or year: revenue, cost of goods,
 * expenses and what was left, with the expenses by kind. Without recorded
 * expenses, a pointer to the expenses section instead.
 */
function FinanceSection({ report, period }: { report: FinanceDto; period: string }) {
  if (!report.has.expenses) {
    return (
      <div id="finance">
      <Card className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="font-medium text-ink">Զուտ շահույթ</p>
          <p className="mt-0.5 text-sm text-ink-muted">Գրեք ծախսերը (հարկեր, վարձ, աշխատավարձ, գնումներ…), և այստեղ կտեսնեք զուտ շահույթը ըստ օրերի, ամիսների, եռամսյակների և տարիների։</p>
        </div>
        <Link href={SECTIONS.expenses.base} className={buttonClasses("secondary")}>
          Ավելացնել ծախսեր
        </Link>
      </Card>
      </div>
    );
  }

  return (
    <section id="finance" className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold text-ink">Զուտ շահույթ</h2>
          <p className="mt-1 max-w-2xl text-sm text-ink-muted">
            Եկամուտ − ապրանքի ինքնարժեք − ծախսեր։ Շահույթից դուրս ծախսերը (սարքավորում, վարկ…) հանվում են միայն «Մնացած գումարից»։
          </p>
        </div>
        <FinanceNav base="/admin/statistics" report={report} keep={{ period }} />
      </div>
      <FinanceTiles period={report.current} previous={report.current.previous} withCost={report.has.cost} />
      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
          <p className="mb-3 text-sm font-medium text-ink">Զուտ շահույթ ըստ ժամանակահատվածների</p>
          <NetProfitChart report={report} />
        </Card>
        <Card>
          <p className="mb-3 text-sm font-medium text-ink">Ծախսեր ըստ տեսակների՝ {report.current.label}</p>
          <FinanceCategories categories={report.current.categories} />
        </Card>
      </div>
      <FinanceTable report={report} />
      <FinanceNotes notes={report.notes} />
    </section>
  );
}

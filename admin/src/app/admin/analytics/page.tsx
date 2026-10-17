import Link from "next/link";
import { Sparkles } from "lucide-react";
import { requirePermission } from "@/lib/adminAuth";
import { getAnalytics, type AnalyticsPeriod, type AnalyticsTotalsDto } from "@/lib/adminApi";
import { LEAD_STATUS_LABELS, LEAD_STATUSES } from "../_components/leadStatus";
import {
  buttonClasses,
  Card,
  cn,
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
  const analytics = await getAnalytics(period);

  return (
    <div className="space-y-8">
      <PageHeader
        title="Վերլուծություն"
        description="Ձեր պատվերների և եկամտի ամփոփումը։ Եկամուտը հաշվվում է կատարված պատվերների վերջնական գնից։"
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

      {analytics ? (
        <>
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
    </div>
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

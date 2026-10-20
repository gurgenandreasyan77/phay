import Link from "next/link";
import { ArrowRight } from "lucide-react";
import { requireAdmin } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import {
  getDashboard,
  type DashboardDto,
  type HomeFiguresDto,
  type HomeSectionDto,
  type HomeUpcomingItemDto,
} from "@/lib/adminApi";
import { bucketLabel, bucketTitle, weekdayShort } from "./_components/analytics/BarChart";
import { formatMoment, formatMoney, formatNumber, SECTIONS, tablePath, tablesPath } from "./_components/tables/shared";
import { LEAD_STATUS_LABELS, LEAD_STATUS_TONES } from "./_components/leadStatus";
import { Badge, buttonClasses, Card, cn, EmptyCard, EmptyRow, PageHeader, TableWrap, Th, Thead, Td, Tr } from "./_components/ui";

export const dynamic = "force-dynamic";

/**
 * The business home: what came in today, this week and this month, the net
 * profit, the orders coming up and the site's orders. Read from the
 * business's own tables, expenses and site orders (GET /admin/dashboard);
 * money only for roles that see it.
 */
export default async function AdminDashboardPage() {
  const me = await requireAdmin();
  const dashboard = await getDashboard();

  return (
    <div className="space-y-8">
      <PageHeader
        title="Վահանակ"
        description={dashboard ? `Այսօր՝ ${bucketTitle(dashboard.today)}` : undefined}
        actions={
          <>
            <Link href="/admin/calendar" className={buttonClasses("secondary")}>
              Օրացույց
            </Link>
            {can(me, "insights") ? (
              <Link href="/admin/statistics" className={buttonClasses("secondary")}>
                Վիճակագրություն
              </Link>
            ) : null}
          </>
        }
      />

      {dashboard ? (
        <>
          {dashboard.finance ? <FinanceBlock finance={dashboard.finance} today={dashboard.today} /> : null}

          {dashboard.sections.map((section) => (
            <SectionBlock key={section.section} section={section} />
          ))}

          {dashboard.sections.length === 0 && !dashboard.site ? (
            <EmptyCard>Պատվերների և վաճառքների բաժինները դեռ միացված չեն։ Գրեք մեզ աջակցության չատում։</EmptyCard>
          ) : null}

          {dashboard.upcoming ? <UpcomingBlock upcoming={dashboard.upcoming} today={dashboard.today} /> : null}

          {dashboard.site ? <SiteBlock site={dashboard.site} money={dashboard.money} /> : null}
        </>
      ) : (
        <p className="text-sm text-danger">Չհաջողվեց բեռնել տվյալները։</p>
      )}
    </div>
  );
}

/* --------------------------------------------------------------- helpers */

/** Change against the same days of last month, in percent; null when there's nothing to compare. */
function change(current: number, previous: number): number | null {
  if (previous === 0) return null;
  return Math.round(((current - previous) / Math.abs(previous)) * 100);
}

function Change({ current, previous }: { current: number; previous: number }) {
  const value = change(current, previous);
  if (value === null) return null;
  return (
    <span className={cn("text-xs font-medium", value > 0 ? "text-success" : value < 0 ? "text-danger" : "text-ink-muted")}>
      {value > 0 ? "+" : ""}
      {value}%
    </span>
  );
}

/** "1–19 հոկտեմբերի": the days of the month so far. */
function monthSoFar(today: string): string {
  const [day, ...rest] = bucketTitle(today).split(",")[0].split(" ");
  return day === "1" ? `${day} ${rest.join(" ")}` : `1–${day} ${rest.join(" ")}`;
}

/** "Այսօր", "Վաղը" or "Ուրբ, 23 հոկ": short enough for one line. */
function dayLabel(date: string, today: string): string {
  const tomorrow = new Date(`${today}T00:00:00Z`);
  tomorrow.setUTCDate(tomorrow.getUTCDate() + 1);
  if (date === today) return "Այսօր";
  if (date === tomorrow.toISOString().slice(0, 10)) return "Վաղը";
  const weekday = (new Date(`${date}T00:00:00Z`).getUTCDay() + 6) % 7;
  return `${weekdayShort(weekday + 1)}, ${bucketLabel(date)}`;
}

/* ---------------------------------------------------------------- blocks */

function FinanceBlock({ finance, today }: { finance: NonNullable<DashboardDto["finance"]>; today: string }) {
  const cards = [
    {
      label: "Եկամուտ",
      value: finance.revenue,
      previous: finance.previous.revenue,
      good: "up",
    },
    {
      label: "Ծախսեր",
      value: finance.expenses + finance.cogs,
      previous: finance.previous.expenses + finance.previous.cogs,
      good: "down",
    },
    {
      label: "Զուտ շահույթ",
      value: finance.netProfit,
      previous: finance.previous.netProfit,
      good: "up",
    },
  ] as const;

  return (
    <section className="space-y-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-base font-semibold text-ink">Այս ամիս · {monthSoFar(today)}</h2>
        <Link href="/admin/expenses" className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
          Ծախսեր և շահույթ <ArrowRight className="h-4 w-4" />
        </Link>
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        {cards.map((card) => (
          <Card key={card.label} className={card.label === "Զուտ շահույթ" ? "border-primary-light bg-primary-light" : undefined}>
            <p className="text-sm text-ink-muted">{card.label}</p>
            <p
              className={cn(
                "mt-1 text-2xl font-semibold",
                card.label === "Զուտ շահույթ" && card.value < 0 ? "text-danger" : "text-ink",
              )}
            >
              {formatMoney(card.value)}
            </p>
            <p className="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-ink-muted">
              <span>նախորդ ամսվա նույն օրերին՝ {formatMoney(card.previous)}</span>
              {card.good === "up" ? <Change current={card.value} previous={card.previous} /> : null}
            </p>
            {card.good === "down" && finance.cogs > 0 ? (
              <p className="text-xs text-ink-faint">որից ապրանքի ինքնարժեքը՝ {formatMoney(finance.cogs)}</p>
            ) : null}
          </Card>
        ))}
      </div>
      {!finance.has.expenses ? (
        <p className="text-sm text-ink-muted">
          Ծախսեր դեռ չեք գրանցել, ուստի զուտ շահույթը հավասար է եկամտին։{" "}
          <Link href="/admin/expenses" className="text-primary hover:underline">
            Ավելացնել ծախսերը
          </Link>
        </p>
      ) : null}
    </section>
  );
}

function Figure({ label, figures, money }: { label: string; figures: HomeFiguresDto; money: boolean }) {
  return (
    <div>
      <p className="text-sm text-ink-muted">{label}</p>
      <p className="mt-1 text-3xl font-semibold text-ink">{formatNumber(figures.count)}</p>
      {money ? <p className="text-sm text-ink-muted">{formatMoney(figures.amount)}</p> : null}
    </div>
  );
}

function SectionBlock({ section }: { section: HomeSectionDto }) {
  const info = SECTIONS[section.section];
  const money = section.hasAmount;

  return (
    <section className="space-y-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-base font-semibold text-ink">{info.label}</h2>
        <Link href={tablesPath(section.section)} className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
          Աղյուսակները <ArrowRight className="h-4 w-4" />
        </Link>
      </div>

      {section.tables === 0 ? (
        <Card className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-ink-muted">
            {info.label} դեռ չկան։ Ստեղծեք աղյուսակ կամ բեռնեք ֆայլ, և թվերն այստեղ կհայտնվեն։
          </p>
          <Link href={tablePath(section.section)} className={buttonClasses("primary", "sm")}>
            Ստեղծել աղյուսակ
          </Link>
        </Card>
      ) : (
        <Card>
          <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <Figure label="Այսօր" figures={section.today} money={money} />
            <Figure label="Այս շաբաթ" figures={section.week} money={money} />
            <div>
              <Figure label="Այս ամիս" figures={section.month} money={money} />
              <p className="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-ink-muted">
                <span>
                  նախորդ ամսվա նույն օրերին՝ {formatNumber(section.previousMonth.count)}
                  {money ? ` · ${formatMoney(section.previousMonth.amount)}` : ""}
                </span>
                <Change
                  current={money ? (section.month.amount ?? 0) : section.month.count}
                  previous={money ? (section.previousMonth.amount ?? 0) : section.previousMonth.count}
                />
              </p>
            </div>
          </div>
          <p className="mt-4 border-t border-border pt-3 text-xs text-ink-faint">
            {section.tables} աղյուսակ · {formatNumber(section.rows)} տող
            {section.lastAt ? ` · վերջին գրառումը՝ ${formatMoment(section.lastAt)}` : ""}
          </p>
        </Card>
      )}
    </section>
  );
}

function UpcomingBlock({ upcoming, today }: { upcoming: NonNullable<DashboardDto["upcoming"]>; today: string }) {
  const href = (item: HomeUpcomingItemDto) => (item.kind === "site" ? "/admin/orders/site" : tablePath("orders", item.tableId));

  return (
    <section className="space-y-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-base font-semibold text-ink">
          Առաջիկա պատվերներ · {upcoming.days} օր
          {upcoming.total > upcoming.items.length ? (
            <span className="ml-2 text-sm font-normal text-ink-muted">
              ցույց է տրված {upcoming.items.length}-ը {upcoming.total}-ից
            </span>
          ) : null}
        </h2>
        <Link href="/admin/calendar" className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
          Օրացույց <ArrowRight className="h-4 w-4" />
        </Link>
      </div>

      {upcoming.items.length === 0 ? (
        <EmptyCard>Առաջիկա {upcoming.days} օրում պատվերներ չկան։</EmptyCard>
      ) : (
        <div className="divide-y divide-border rounded-lg border border-border bg-surface">
          {upcoming.items.map((item) => (
            <Link
              key={`${item.kind}-${item.id}`}
              href={href(item)}
              className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-surface-secondary"
            >
              <span className="w-full shrink-0 text-sm text-ink-muted sm:w-32">
                {dayLabel(item.date, today)}
                {item.time ? `, ${item.time}` : ""}
              </span>
              <span className="min-w-0 flex-1 basis-48">
                <span className="font-medium text-ink">{item.title}</span>
                {item.customer ? <span className="text-ink-muted"> · {item.customer}</span> : null}
                {item.performer ? <span className="text-ink-faint"> · {item.performer}</span> : null}
              </span>
              {item.kind === "site" ? (
                <Badge tone={LEAD_STATUS_TONES[item.status as keyof typeof LEAD_STATUS_TONES] ?? "default"}>Կայքից</Badge>
              ) : item.status ? (
                <Badge>{item.status}</Badge>
              ) : null}
              {item.amount !== null ? <span className="text-sm font-medium text-ink">{formatMoney(item.amount)}</span> : null}
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}

function SiteBlock({ site, money }: { site: NonNullable<DashboardDto["site"]>; money: boolean }) {
  const cards = [
    { label: "Նոր այսօր", value: site.newToday, accent: true },
    {
      label: "Սպասում են պատասխանի",
      value: site.waiting,
      accent: site.waiting > 0,
    },
    { label: "Ընթացքի մեջ", value: site.inWork, accent: false },
    { label: "Կատարված այս ամիս", value: site.completedMonth, accent: false },
  ];

  return (
    <section className="space-y-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-base font-semibold text-ink">Պատվերներ կայքից</h2>
        <Link href="/admin/orders/site" className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
          Բոլորը <ArrowRight className="h-4 w-4" />
        </Link>
      </div>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {cards.map((card) => (
          <Card key={card.label} className={card.accent ? "border-primary-light bg-primary-light" : undefined}>
            <p className={cn("text-sm", card.accent ? "text-primary/80" : "text-ink-muted")}>{card.label}</p>
            <p className={cn("mt-1 text-2xl font-semibold", card.accent ? "text-primary" : "text-ink")}>{card.value}</p>
            {card.label === "Կատարված այս ամիս" && money && site.revenueMonth !== null ? (
              <p className="text-xs text-ink-muted">{formatMoney(site.revenueMonth)}</p>
            ) : null}
          </Card>
        ))}
      </div>

      <TableWrap>
        <Thead>
          <Th>Անուն</Th>
          <Th>Հեռախոս</Th>
          <Th>Կարգավիճակ</Th>
          <Th>Ամսաթիվ</Th>
        </Thead>
        <tbody>
          {site.recent.map((lead) => (
            <Tr key={lead.id}>
              <Td className="font-medium">{lead.name}</Td>
              <Td className="text-ink-muted">{lead.phone}</Td>
              <Td>
                <Badge tone={LEAD_STATUS_TONES[lead.status]}>{LEAD_STATUS_LABELS[lead.status]}</Badge>
              </Td>
              <Td className="text-ink-muted">{lead.createdAt ? new Date(lead.createdAt).toLocaleDateString("hy-AM") : "—"}</Td>
            </Tr>
          ))}
          {site.recent.length === 0 ? <EmptyRow colSpan={4}>Պատվերներ դեռ չկան։</EmptyRow> : null}
        </tbody>
      </TableWrap>
    </section>
  );
}

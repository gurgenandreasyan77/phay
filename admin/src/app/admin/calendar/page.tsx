import Link from "next/link";
import type { CSSProperties } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { requirePermission } from "@/lib/adminAuth";
import { can, isPlatformStaff } from "@/lib/roles";
import { getCalendar, getGoogleState } from "@/lib/adminApi";
import { buttonClasses, cn, EmptyCard, PageHeader } from "../_components/ui";
import { bucketTitle, weekdayShort } from "../_components/analytics/BarChart";
import { formatMoney, SECTIONS, tablePath } from "../_components/tables/shared";
import { DayView } from "./DayView";
import { addDays, addMonths, isDay, today, visibleDays, type CalendarView } from "./dates";
import { GOOGLE_DOT, groupByDay, markShape, tableColor, type CalendarDay } from "./entries";
import { GoogleSync } from "./GoogleSync";
import styles from "./calendar.module.css";

export const dynamic = "force-dynamic";

function first(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

function href(view: CalendarView, day: string, open = false): string {
  return `/admin/calendar?view=${view}&day=${day}${open ? "&open=1" : ""}`;
}

/**
 * The calendar: the orders of each day, from the order tables (rows with a
 * date) and from the site, and the day's sales from the sales tables, apart:
 * services as round dots, sales as amber squares, each with its own count,
 * so a business that does both sees how many of each a day had. A day
 * opens on the right, where a row is added to a table or deleted from it,
 * and so from the analytics too. On a phone a day opens in a sheet from
 * the bottom.
 */
export default async function AdminCalendarPage(props: PageProps<"/admin/calendar">) {
  const me = await requirePermission("orders");

  const searchParams = await props.searchParams;
  const now = today();
  const rawDay = first(searchParams.day);
  const day = isDay(rawDay) ? rawDay : now;
  const view: CalendarView = first(searchParams.view) === "week" ? "week" : "month";
  // A day tapped in the grid (not one opened by a link): its sheet, on a phone.
  const open = first(searchParams.open) === "1";

  const days = visibleDays(day, view);
  const [data, google] = await Promise.all([getCalendar(days[0], days[days.length - 1]), getGoogleState()]);
  const byDay = data ? groupByDay(data) : new Map<string, CalendarDay>();
  const step = (n: number) => (view === "week" ? addDays(day, 7 * n) : addMonths(day, n));
  const title =
    view === "week"
      ? `${bucketTitle(days[0]).split(",")[0]} – ${bucketTitle(days[6]).split(",")[0]}`
      : bucketTitle(day.slice(0, 7));

  return (
    <div className="space-y-6">
      <PageHeader
        title="Օրացույց"
        description="Ձեր պատվերներն ու վաճառքներն ըստ օրերի՝ աղյուսակներից, կայքից և Google օրացույցից։ Ծառայությունները՝ կանաչ կետերով, վաճառքները՝ դեղին քառակուսիներով։ Ընտրեք օրը՝ ավելացնելու կամ ջնջելու համար. փոփոխությունները միանգամից երևում են աղյուսակում և վերլուծության մեջ։"
      />

      {google ? (
        <GoogleSync
          state={google}
          canMakeTable={can(me, "catalog")}
          staff={isPlatformStaff(me)}
          notice={first(searchParams.google) ?? null}
        />
      ) : null}

      <div className="flex flex-wrap items-center gap-2">
        <Link href={href(view, step(-1))} className={buttonClasses("secondary", "sm")} aria-label="Նախորդ">
          <ChevronLeft className="h-4 w-4" />
        </Link>
        <Link href={href(view, step(1))} className={buttonClasses("secondary", "sm")} aria-label="Հաջորդ">
          <ChevronRight className="h-4 w-4" />
        </Link>
        <Link href={href(view, now)} className={buttonClasses("ghost", "sm")}>
          Այսօր
        </Link>
        <h2 className="ml-2 text-lg font-semibold text-ink">{title}</h2>
        <div className="ml-auto inline-flex rounded-md border border-border bg-surface p-0.5 text-sm">
          {(["month", "week"] as const).map((v) => (
            <Link
              key={v}
              href={href(v, day)}
              className={cn(
                "rounded px-3 py-1",
                v === view ? "bg-primary text-primary-foreground" : "text-ink-muted hover:text-ink",
              )}
            >
              {v === "month" ? "Ամիս" : "Շաբաթ"}
            </Link>
          ))}
        </div>
      </div>

      {!data ? (
        <EmptyCard>Չհաջողվեց բեռնել օրացույցը։ Թարմացրեք էջը։</EmptyCard>
      ) : (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="space-y-3">
            <div className="overflow-hidden rounded-lg border border-border bg-surface">
              <div className="grid grid-cols-7 border-b border-border bg-surface-secondary text-xs font-medium text-ink-muted">
                {[1, 2, 3, 4, 5, 6, 7].map((d) => (
                  <div key={d} className="px-2 py-2 text-center">
                    {weekdayShort(d)}
                  </div>
                ))}
              </div>
              {/* A new month or week remounts the grid, and its days come in again. */}
              <div key={`${view}-${days[0]}`} className="grid grid-cols-7">
                {days.map((d, i) => {
                  const info = byDay.get(d);
                  const services = info?.totals.services.count ?? 0;
                  const sales = info?.totals.sales.count ?? 0;
                  const count = services + sales + (info?.google.length ?? 0);
                  const outside = view === "month" && d.slice(0, 7) !== day.slice(0, 7);

                  return (
                    <Link
                      key={d}
                      href={href(view, d, count > 0)}
                      scroll={false}
                      style={{ "--i": i } as CSSProperties}
                      className={cn(
                        styles.cell,
                        "flex flex-col gap-1 border-b border-r border-border p-1.5 text-left hover:bg-primary-light/50 sm:p-2 [&:nth-child(7n)]:border-r-0",
                        view === "week" ? "min-h-64" : "min-h-20 sm:min-h-24",
                        outside && "bg-surface-secondary/50 text-ink-faint",
                        d === day && "bg-primary-light ring-2 ring-inset ring-primary",
                      )}
                    >
                      <span
                        className={cn(
                          "inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium",
                          d === now ? cn(styles.today, "bg-primary text-primary-foreground") : outside ? "text-ink-faint" : "text-ink",
                        )}
                      >
                        {Number(d.slice(8))}
                      </span>
                      {view === "week" ? (
                        <ul className="space-y-1 text-xs">
                          {info?.services.map((e, j) => (
                            <li key={e.id} className={cn(styles.item, "flex items-start gap-1")} style={{ "--j": j } as CSSProperties}>
                              <span className={cn("mt-1 h-1.5 w-1.5 shrink-0", markShape("orders"), tableColor(data, e.tableId))} />
                              <span className="min-w-0 break-words text-ink">
                                {e.time ? <span className="text-ink-muted">{e.time} </span> : null}
                                {e.title}
                              </span>
                            </li>
                          ))}
                          {info?.leads.map((l) => (
                            <li key={`lead-${l.id}`} className="flex items-start gap-1">
                              <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-info" />
                              <span className="min-w-0 break-words text-info">{l.service ?? l.name}</span>
                            </li>
                          ))}
                          {info?.sales.map((e, j) => (
                            <li
                              key={e.id}
                              className={cn(styles.item, "flex items-start gap-1 rounded-sm bg-warning-light/70 px-1 py-0.5")}
                              style={{ "--j": (info?.services.length ?? 0) + j } as CSSProperties}
                            >
                              <span className={cn("mt-1 h-1.5 w-1.5 shrink-0", markShape("sales"), tableColor(data, e.tableId))} />
                              <span className="min-w-0 break-words text-ink">
                                {e.title}
                                {e.quantity ? <span className="text-ink-muted"> × {e.quantity}</span> : null}
                              </span>
                            </li>
                          ))}
                          {info?.google.map((g) => (
                            <li key={`google-${g.id}`} className="flex items-start gap-1">
                              <span className={cn("mt-1 h-1.5 w-1.5 shrink-0 rounded-full", GOOGLE_DOT)} />
                              <span className="min-w-0 break-words text-ink">
                                {g.time ? <span className="text-ink-muted">{g.time} </span> : null}
                                {g.title}
                              </span>
                            </li>
                          ))}
                        </ul>
                      ) : count > 0 ? (
                        <>
                          {services > 0 || (info?.google.length ?? 0) > 0 ? (
                            <span className={cn(styles.count, "flex items-center gap-1 text-xs font-medium text-ink")}>
                              <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                              {services + (info?.google.length ?? 0)} <span className="hidden sm:inline">պատվեր</span>
                            </span>
                          ) : null}
                          {sales > 0 ? (
                            <span className={cn(styles.count, "flex items-center gap-1 text-xs font-medium text-warning")}>
                              <span className="h-1.5 w-1.5 rounded-[2px] bg-chart-3" />
                              {sales} <span className="hidden sm:inline">վաճառք</span>
                            </span>
                          ) : null}
                          {info && info.amount > 0 ? (
                            <span className="hidden text-xs text-ink-muted sm:block">{formatMoney(info.amount)}</span>
                          ) : null}
                          <span className="mt-auto flex flex-wrap gap-0.5">
                            {info?.services.slice(0, 8).map((e, j) => (
                              <span
                                key={e.id}
                                className={cn(styles.dot, "h-1.5 w-1.5", markShape("orders"), tableColor(data, e.tableId))}
                                style={{ "--i": i, "--j": j } as CSSProperties}
                              />
                            ))}
                            {info?.leads.slice(0, 4).map((l, j) => (
                              <span
                                key={`lead-${l.id}`}
                                className={cn(styles.dot, "h-1.5 w-1.5 rounded-full bg-info")}
                                style={{ "--i": i, "--j": j + 8 } as CSSProperties}
                              />
                            ))}
                            {info?.sales.slice(0, 8).map((e, j) => (
                              <span
                                key={e.id}
                                className={cn(styles.dot, "h-1.5 w-1.5", markShape("sales"), tableColor(data, e.tableId))}
                                style={{ "--i": i, "--j": j + 12 } as CSSProperties}
                              />
                            ))}
                            {info?.google.slice(0, 6).map((g, j) => (
                              <span
                                key={`google-${g.id}`}
                                className={cn(styles.dot, "h-1.5 w-1.5 rounded-full", GOOGLE_DOT)}
                                style={{ "--i": i, "--j": j + 20 } as CSSProperties}
                              />
                            ))}
                          </span>
                        </>
                      ) : null}
                    </Link>
                  );
                })}
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-muted">
              {data.tables.map((t) => (
                <Link key={t.id} href={tablePath(t.section, t.id)} className="inline-flex items-center gap-1.5 hover:text-ink">
                  <span className={cn("h-2 w-2", markShape(t.section), tableColor(data, t.id))} />
                  {t.name}
                  {t.section === "sales" ? <span className="text-ink-faint">(վաճառք)</span> : null}
                </Link>
              ))}
              {data.leads.length > 0 ? (
                <Link href={`${SECTIONS.orders.base}/site`} className="inline-flex items-center gap-1.5 hover:text-ink">
                  <span className="h-2 w-2 rounded-full bg-info" />
                  Կայքից
                </Link>
              ) : null}
              {data.google.length > 0 ? (
                <span className="inline-flex items-center gap-1.5">
                  <span className={cn("h-2 w-2 rounded-full", GOOGLE_DOT)} />
                  Google օրացույց (դեռ աղյուսակում չէ)
                </span>
              ) : null}
              {data.truncated ? <span className="text-warning">Ցուցադրված են ոչ բոլոր տողերը. ընտրեք շաբաթը։</span> : null}
            </div>
          </div>

          <DayView
            key={`${day}-${open}`}
            open={open}
            closeHref={href(view, day)}
            day={day}
            title={bucketTitle(day)}
            tables={data.tables}
            services={byDay.get(day)?.services ?? []}
            sales={byDay.get(day)?.sales ?? []}
            leads={byDay.get(day)?.leads ?? []}
            google={byDay.get(day)?.google ?? []}
            colors={Object.fromEntries(data.tables.map((t) => [t.id, tableColor(data, t.id)]))}
            canDesign={can(me, "catalog")}
          />
        </div>
      )}
    </div>
  );
}

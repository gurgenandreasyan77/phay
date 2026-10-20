import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { requirePermission } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import { getCalendar } from "@/lib/adminApi";
import { buttonClasses, cn, EmptyCard, PageHeader } from "../_components/ui";
import { bucketTitle, weekdayShort } from "../_components/analytics/BarChart";
import { formatMoney, SECTIONS, tablePath } from "../_components/tables/shared";
import { DayPanel } from "./DayPanel";
import { addDays, addMonths, isDay, today, visibleDays, type CalendarView } from "./dates";
import { groupByDay, tableColor, type CalendarDay } from "./entries";

export const dynamic = "force-dynamic";

function first(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

function href(view: CalendarView, day: string): string {
  return `/admin/calendar?view=${view}&day=${day}`;
}

/**
 * The calendar: the orders of each day, from the order tables (rows with a
 * date) and from the site. A day opens on the right, where an order is
 * added to a table or deleted from it, and so from the analytics too.
 */
export default async function AdminCalendarPage(props: PageProps<"/admin/calendar">) {
  const me = await requirePermission("orders");

  const searchParams = await props.searchParams;
  const now = today();
  const rawDay = first(searchParams.day);
  const day = isDay(rawDay) ? rawDay : now;
  const view: CalendarView = first(searchParams.view) === "week" ? "week" : "month";

  const days = visibleDays(day, view);
  const data = await getCalendar(days[0], days[days.length - 1]);
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
        description="Ձեր պատվերներն ըստ օրերի՝ պատվերների աղյուսակներից և կայքից։ Ընտրեք օրը՝ պատվեր ավելացնելու կամ ջնջելու համար. փոփոխությունները միանգամից երևում են աղյուսակում և վերլուծության մեջ։"
      />

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
              <div className="grid grid-cols-7">
                {days.map((d) => {
                  const info = byDay.get(d);
                  const count = (info?.entries.length ?? 0) + (info?.leads.length ?? 0);
                  const outside = view === "month" && d.slice(0, 7) !== day.slice(0, 7);

                  return (
                    <Link
                      key={d}
                      href={href(view, d)}
                      scroll={false}
                      className={cn(
                        "flex flex-col gap-1 border-b border-r border-border p-1.5 text-left transition-colors hover:bg-primary-light/50 sm:p-2 [&:nth-child(7n)]:border-r-0",
                        view === "week" ? "min-h-64" : "min-h-20 sm:min-h-24",
                        outside && "bg-surface-secondary/50 text-ink-faint",
                        d === day && "bg-primary-light ring-2 ring-inset ring-primary",
                      )}
                    >
                      <span
                        className={cn(
                          "inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium",
                          d === now ? "bg-primary text-primary-foreground" : outside ? "text-ink-faint" : "text-ink",
                        )}
                      >
                        {Number(d.slice(8))}
                      </span>
                      {view === "week" ? (
                        <ul className="space-y-1 text-xs">
                          {info?.entries.map((e) => (
                            <li key={e.id} className="flex items-start gap-1">
                              <span className={cn("mt-1 h-1.5 w-1.5 shrink-0 rounded-full", tableColor(data, e.tableId))} />
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
                        </ul>
                      ) : count > 0 ? (
                        <>
                          <span className="text-xs font-medium text-ink">
                            {count} <span className="hidden sm:inline">պատվեր</span>
                          </span>
                          {info && info.amount > 0 ? (
                            <span className="hidden text-xs text-ink-muted sm:block">{formatMoney(info.amount)}</span>
                          ) : null}
                          <span className="mt-auto flex flex-wrap gap-0.5">
                            {info?.entries.slice(0, 8).map((e) => (
                              <span key={e.id} className={cn("h-1.5 w-1.5 rounded-full", tableColor(data, e.tableId))} />
                            ))}
                            {info?.leads.slice(0, 4).map((l) => (
                              <span key={`lead-${l.id}`} className="h-1.5 w-1.5 rounded-full bg-info" />
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
                <Link key={t.id} href={tablePath("orders", t.id)} className="inline-flex items-center gap-1.5 hover:text-ink">
                  <span className={cn("h-2 w-2 rounded-full", tableColor(data, t.id))} />
                  {t.name}
                </Link>
              ))}
              {data.leads.length > 0 ? (
                <Link href={`${SECTIONS.orders.base}/site`} className="inline-flex items-center gap-1.5 hover:text-ink">
                  <span className="h-2 w-2 rounded-full bg-info" />
                  Կայքից
                </Link>
              ) : null}
              {data.truncated ? <span className="text-warning">Ցուցադրված են ոչ բոլոր պատվերները. ընտրեք շաբաթը։</span> : null}
            </div>
          </div>

          <DayPanel
            key={day}
            day={day}
            title={bucketTitle(day)}
            tables={data.tables}
            entries={byDay.get(day)?.entries ?? []}
            leads={byDay.get(day)?.leads ?? []}
            colors={Object.fromEntries(data.tables.map((t) => [t.id, tableColor(data, t.id)]))}
            canDesign={can(me, "catalog")}
          />
        </div>
      )}
    </div>
  );
}

import Link from "next/link";
import { requirePermission } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import { getEmployees, getEmployeeStats, type EmployeeFiguresDto, type EmployeeStatsDto } from "@/lib/adminApi";
import { EmptyCard, PageHeader, SectionCard, TableWrap, Td, Th, Thead, Tr, cn } from "../_components/ui";
import { formatMoney, SECTIONS } from "../_components/tables/shared";
import { PeriodNav, periodFromParams } from "../_components/tables/PeriodNav";
import { AddEmployeeButton, EmployeesManager } from "./EmployeesManager";

export const dynamic = "force-dynamic";

/**
 * Employees: who does the business's orders. The owner sees how many
 * orders each one did in a period and what they brought in; the owner and
 * the admin keep the list. A row is someone's when its «Կատարող» column
 * holds their name.
 */
export default async function AdminEmployeesPage(props: PageProps<"/admin/employees">) {
  const me = await requirePermission("settings");
  const insights = can(me, "insights");
  const params = await props.searchParams;
  const current = periodFromParams(params);
  const [employees, stats] = await Promise.all([
    getEmployees(),
    insights ? getEmployeeStats(current ? { period: current.kind, date: current.date } : {}) : Promise.resolve(null),
  ]);
  const query = current ? `?period=${current.kind}&date=${current.date}` : "?period=all";

  return (
    <div className="space-y-8">
      <PageHeader
        title="Աշխատակիցներ"
        description="Ով է կատարում պատվերները։ Պատվերների աղյուսակի «Կատարող» սյունակում ընտրեք աշխատակցին, և այստեղ կերևա, թե ով քանի պատվեր է արել, որքան եկամուտ է բերել և որքան ծախս է եղել։"
      />

      {stats ? (
        <SectionCard title="Ով ինչ է արել" description="Եկամուտը՝ պատվերի գումարը, ծախսերը՝ ինքնարժեք × քանակ, շահույթը՝ եկամուտ − ծախսեր։">
          <PeriodNav base="/admin/employees" current={current} />
          {!stats.hasPerformerColumn ? (
            <EmptyCard>
              Պատվերների աղյուսակներում դեռ չկա «Կատարող» սյունակ։ Բացեք{" "}
              <Link href={SECTIONS.orders.base} className="font-medium underline">
                աղյուսակը
              </Link>{" "}
              → «Սյունակներ և կարգավորումներ» → «+ Ավելացնել պատրաստի սյունակ» → «Կատարող»։
            </EmptyCard>
          ) : (
            <StatsTable stats={stats} query={query} />
          )}
        </SectionCard>
      ) : null}

      <SectionCard title="Ցանկ" description="Անունը պետք է համընկնի «Կատարող» սյունակում գրվածին (մեծատառ-փոքրատառը նշանակություն չունի)։ Անունը փոխելիս այն փոխվում է նաև աղյուսակներում։">
        <EmployeesManager employees={employees} />
      </SectionCard>
    </div>
  );
}

function Money({ value }: { value: number | null }) {
  return <>{value === null ? "—" : formatMoney(value)}</>;
}

function FiguresCells({ figures, stats }: { figures: EmployeeFiguresDto; stats: EmployeeStatsDto }) {
  return (
    <>
      <Td className="text-right tabular-nums">{figures.orders}</Td>
      {stats.hasAmount ? (
        <Td className="whitespace-nowrap text-right tabular-nums">
          <Money value={figures.revenue} />
        </Td>
      ) : null}
      {stats.hasCost ? (
        <>
          <Td className="whitespace-nowrap text-right tabular-nums">
            <Money value={figures.costs} />
          </Td>
          <Td className="whitespace-nowrap text-right tabular-nums">
            <Money value={figures.profit} />
          </Td>
        </>
      ) : null}
      {stats.hasAmount ? (
        <Td className="whitespace-nowrap text-right tabular-nums">
          <Money value={figures.averageCheck} />
        </Td>
      ) : null}
    </>
  );
}

function StatsTable({ stats, query }: { stats: EmployeeStatsDto; query: string }) {
  const share = (orders: number) => (stats.total.orders > 0 ? Math.round((orders / stats.total.orders) * 100) : 0);
  const shown = stats.employees.filter((e) => e.isActive || e.orders > 0);

  return (
    <TableWrap>
      <Thead>
        <Th>Աշխատակից</Th>
        <Th className="text-right">Պատվերներ</Th>
        {stats.hasAmount ? <Th className="text-right">Եկամուտ</Th> : null}
        {stats.hasCost ? (
          <>
            <Th className="text-right">Ծախսեր</Th>
            <Th className="text-right">Շահույթ</Th>
          </>
        ) : null}
        {stats.hasAmount ? <Th className="text-right">Միջին չեկ</Th> : null}
      </Thead>
      <tbody>
        {shown.map((e) => (
          <Tr key={e.id}>
            <Td>
              <Link href={`/admin/employees/${e.id}${query}`} className={cn("font-medium hover:text-primary", e.isActive ? "text-ink" : "text-ink-muted")}>
                {e.name}
              </Link>
              <span className="block text-xs text-ink-faint">
                {[e.position, e.isActive ? null : "չի աշխատում", e.orders > 0 ? `${share(e.orders)}% պատվերների` : null].filter(Boolean).join(" · ") || " "}
              </span>
            </Td>
            <FiguresCells figures={e} stats={stats} />
          </Tr>
        ))}
        {stats.unknown.map((u) => (
          <Tr key={`unknown-${u.name}`}>
            <Td>
              <span className="font-medium text-ink">{u.name}</span>
              <span className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-warning">
                Ցանկում չկա
                <AddEmployeeButton name={u.name} />
              </span>
            </Td>
            <FiguresCells figures={u} stats={stats} />
          </Tr>
        ))}
        {stats.unassigned.orders > 0 ? (
          <Tr>
            <Td className="text-ink-muted">Առանց կատարողի</Td>
            <FiguresCells figures={stats.unassigned} stats={stats} />
          </Tr>
        ) : null}
        <tr className="border-t border-border-strong bg-surface-secondary font-medium">
          <Td>Ընդամենը</Td>
          <FiguresCells figures={stats.total} stats={stats} />
        </tr>
      </tbody>
    </TableWrap>
  );
}

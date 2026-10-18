// Who did a section's orders in a period: orders, revenue, costs and profit
// per employee (the «Կատարող» column), shown on the orders and sales pages.
import Link from "next/link";
import type { EmployeeFiguresDto, EmployeeStatsDto, PeriodKind } from "@/lib/adminApi";
import { cn, SectionCard, TableWrap, Td, Th, Thead, Tr } from "../ui";
import { formatMoney } from "./shared";
import { PeriodNav } from "./PeriodNav";

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

export function PerformerStats({
  stats,
  base,
  current,
}: {
  stats: EmployeeStatsDto;
  /** The page the period links go to. */
  base: string;
  current: { kind: PeriodKind; date: string } | null;
}) {
  const share = (orders: number) => (stats.total.orders > 0 ? Math.round((orders / stats.total.orders) * 100) : 0);
  const shown = stats.employees.filter((e) => e.isActive || e.orders > 0);

  return (
    <SectionCard
      title="Ըստ կատարողների"
      description="Ով քանի պատվեր է արել ընտրված օրում, շաբաթում, ամսում, եռամսյակում կամ տարում։ Եկամուտը՝ պատվերի գումարը, ծախսերը՝ ինքնարժեք × քանակ, շահույթը՝ եկամուտ − ծախսեր։"
    >
      <PeriodNav base={base} current={current} />
      <TableWrap>
        <Thead>
          <Th>Կատարող</Th>
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
                <Link href={`/admin/employees/${e.id}`} className={cn("font-medium hover:text-primary", e.isActive ? "text-ink" : "text-ink-muted")}>
                  {e.name}
                </Link>
                <span className="block text-xs text-ink-faint">
                  {[e.position, e.isActive ? null : "չի աշխատում", e.orders > 0 ? `${share(e.orders)}% պատվերների` : null]
                    .filter(Boolean)
                    .join(" · ") || " "}
                </span>
              </Td>
              <FiguresCells figures={e} stats={stats} />
            </Tr>
          ))}
          {stats.unknown.map((u) => (
            <Tr key={`unknown-${u.name}`}>
              <Td>
                <span className="font-medium text-ink">{u.name}</span>
                <span className="block text-xs text-warning">
                  Չկա{" "}
                  <Link href="/admin/employees" className="underline">
                    աշխատակիցների
                  </Link>{" "}
                  ցանկում
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
    </SectionCard>
  );
}

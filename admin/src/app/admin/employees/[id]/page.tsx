import Link from "next/link";
import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { getEmployee } from "@/lib/adminApi";
import { buttonClasses, Card, EmptyRow, PageHeader, TableWrap, Td, Th, Thead, Tr } from "../../_components/ui";
import { formatMoment, formatMoney, tablePath } from "../../_components/tables/shared";
import { filingLabel } from "../../_components/tables/periods";
import { PeriodNav, periodFromParams } from "../../_components/tables/PeriodNav";

export const dynamic = "force-dynamic";

/** One employee: their figures in a period and the orders they did. */
export default async function AdminEmployeePage(props: PageProps<"/admin/employees/[id]">) {
  await requirePermission("insights");
  const { id } = await props.params;
  const params = await props.searchParams;
  const current = periodFromParams(params);
  const page = Number(typeof params.page === "string" ? params.page : "") || undefined;
  const detail = Number.isInteger(Number(id))
    ? await getEmployee(Number(id), current ? { period: current.kind, date: current.date, page } : { page })
    : null;
  if (!detail) notFound();

  const { employee, figures, rows, meta } = detail;
  const base = `/admin/employees/${employee.id}`;
  const query = current ? `period=${current.kind}&date=${current.date}` : "period=all";
  const cards: [string, string][] = [
    ["Պատվերներ", String(figures.orders)],
    ["Եկամուտ", figures.revenue === null ? "—" : formatMoney(figures.revenue)],
    ["Ծախսեր", figures.costs === null ? "—" : formatMoney(figures.costs)],
    ["Շահույթ", figures.profit === null ? "—" : formatMoney(figures.profit)],
    ["Միջին չեկ", figures.averageCheck === null ? "—" : formatMoney(figures.averageCheck)],
  ];

  return (
    <div className="space-y-6">
      <Link href={`/admin/employees?${query}`} className={buttonClasses("ghost", "sm")}>
        ← Բոլոր աշխատակիցները
      </Link>
      <PageHeader
        title={employee.name}
        description={[employee.position, employee.phone, employee.isActive ? null : "Չի աշխատում"].filter(Boolean).join(" · ") || undefined}
      />
      <PeriodNav base={base} current={current} />

      <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {cards.map(([label, value]) => (
          <Card key={label} className="p-4">
            <p className="text-xs text-ink-muted">{label}</p>
            <p className="mt-1 text-lg font-semibold tabular-nums text-ink">{value}</p>
          </Card>
        ))}
      </div>

      <TableWrap>
        <Thead>
          <Th>Ամսաթիվ</Th>
          <Th>Ծառայություն / ապրանք</Th>
          <Th>Աղյուսակ</Th>
          <Th className="text-right">Գումար</Th>
          <Th className="text-right">Ծախս</Th>
        </Thead>
        <tbody>
          {rows.map((row) => (
            <Tr key={row.id}>
              <Td className="whitespace-nowrap">
                {row.occurredAt ? formatMoment(row.occurredAt) : "—"}
                {row.occurredAt ? <span className="block text-xs text-ink-faint">{filingLabel(row.occurredAt)}</span> : null}
              </Td>
              <Td>
                {row.item ?? "—"}
                {row.status ? <span className="block text-xs text-ink-faint">{row.status}</span> : null}
              </Td>
              <Td>
                <Link href={tablePath(row.table.section, row.table.id)} className="text-ink-muted hover:text-primary">
                  {row.table.name}
                </Link>
              </Td>
              <Td className="whitespace-nowrap text-right tabular-nums">{row.amount === null ? "—" : formatMoney(row.amount)}</Td>
              <Td className="whitespace-nowrap text-right tabular-nums">{row.cost === null ? "—" : formatMoney(row.cost)}</Td>
            </Tr>
          ))}
          {rows.length === 0 ? <EmptyRow colSpan={5}>Այս ժամանակահատվածում պատվերներ չկան։</EmptyRow> : null}
        </tbody>
      </TableWrap>

      {meta.lastPage > 1 ? (
        <div className="flex items-center gap-3 text-sm text-ink-muted">
          {meta.currentPage > 1 ? (
            <Link href={`${base}?${query}&page=${meta.currentPage - 1}`} className={buttonClasses("secondary", "sm")}>
              Նախորդ
            </Link>
          ) : null}
          <span>
            {meta.currentPage} / {meta.lastPage}
          </span>
          {meta.currentPage < meta.lastPage ? (
            <Link href={`${base}?${query}&page=${meta.currentPage + 1}`} className={buttonClasses("secondary", "sm")}>
              Հաջորդ
            </Link>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

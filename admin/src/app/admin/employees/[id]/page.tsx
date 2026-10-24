import Link from "next/link";
import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import { getEmployee } from "@/lib/adminApi";
import { buttonClasses, Card, EmptyRow, PageHeader, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import { formatMoment, formatMoney, tablePath } from "../../_components/tables/shared";
import { filingLabel } from "../../_components/tables/periods";

export const dynamic = "force-dynamic";

/**
 * One employee and every order they did, newest first. The money only for
 * the owner; figures by period are on the orders page.
 */
export default async function AdminEmployeePage(props: PageProps<"/admin/employees/[id]">) {
  const me = await requirePermission("settings");
  const money = can(me, "insights");
  const { id } = await props.params;
  const params = await props.searchParams;
  const page = Number(typeof params.page === "string" ? params.page : "") || undefined;
  const detail = Number.isInteger(Number(id)) ? await getEmployee(Number(id), { page }) : null;
  if (!detail) notFound();

  const { employee, figures, rows, meta } = detail;
  const base = `/admin/employees/${employee.id}`;
  const cards: [string, string][] = [
    ["Պատվերներ", String(figures.orders)],
    ...(money
      ? ([
          ["Եկամուտ", figures.revenue === null ? "—" : formatMoney(figures.revenue)],
          ["Ծախսեր", figures.costs === null ? "—" : formatMoney(figures.costs)],
          ["Շահույթ", figures.profit === null ? "—" : formatMoney(figures.profit)],
          ["Միջին չեկ", figures.averageCheck === null ? "—" : formatMoney(figures.averageCheck)],
        ] as [string, string][])
      : []),
  ];

  return (
    <div className="space-y-6">
      <Link href="/admin/employees" className={buttonClasses("ghost", "sm")}>
        ← Բոլոր աշխատակիցները
      </Link>
      <PageHeader
        title={employee.name}
        description={[employee.position, employee.phone, employee.isActive ? null : "Չի աշխատում"].filter(Boolean).join(" · ") || undefined}
      />
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
          {money ? <Th className="text-right">Գումար</Th> : null}
          {money ? <Th className="text-right">Ծախս</Th> : null}
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
              {money ? (
                <Td className="whitespace-nowrap text-right tabular-nums">{row.amount === null ? "—" : formatMoney(row.amount)}</Td>
              ) : null}
              {money ? (
                <Td className="whitespace-nowrap text-right tabular-nums">{row.cost === null ? "—" : formatMoney(row.cost)}</Td>
              ) : null}
            </Tr>
          ))}
          {rows.length === 0 ? <EmptyRow colSpan={money ? 5 : 3}>Այս աշխատակիցը դեռ պատվերներ չունի։ Նշեք նրան պատվերի «Կատարող» դաշտում։</EmptyRow> : null}
        </tbody>
      </TableWrap>

      {meta.lastPage > 1 ? (
        <div className="flex items-center gap-3 text-sm text-ink-muted">
          {meta.currentPage > 1 ? (
            <Link href={`${base}?page=${meta.currentPage - 1}`} className={buttonClasses("secondary", "sm")}>
              Նախորդ
            </Link>
          ) : null}
          <span>
            {meta.currentPage} / {meta.lastPage}
          </span>
          {meta.currentPage < meta.lastPage ? (
            <Link href={`${base}?page=${meta.currentPage + 1}`} className={buttonClasses("secondary", "sm")}>
              Հաջորդ
            </Link>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

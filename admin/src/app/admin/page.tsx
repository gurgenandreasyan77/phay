import Link from "next/link";
import { requireAdmin } from "@/lib/adminAuth";
import { getDashboard, getDataTables, getFeatureFlagsAdmin, type DataTableSection } from "@/lib/adminApi";
import { formatMoment, formatMoney, SECTIONS, tablesPath } from "./_components/tables/shared";
import { LEAD_STATUS_LABELS, LEAD_STATUS_TONES, LEAD_STATUSES } from "./_components/leadStatus";
import { Badge, buttonClasses, Card, EmptyCard, EmptyRow, PageHeader, TableWrap, Th, Thead, Td, Tr } from "./_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminDashboardPage() {
  await requireAdmin();
  const flags = await getFeatureFlagsAdmin();

  // A business without the orders module (e.g. a shop that sells goods)
  // has no orders from the site: its home is its own tables.
  if (!(flags.find((f) => f.key === "orders")?.active ?? true)) {
    return <TablesHome />;
  }

  const dashboard = await getDashboard();

  return (
    <div className="space-y-8">
      <PageHeader
        title="Վահանակ"
        actions={
          <Link href="/admin/orders/new" className={buttonClasses("primary")}>
            Ավելացնել պատվեր
          </Link>
        }
      />

      {dashboard ? (
        <>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card className="border-primary-light bg-primary-light">
              <p className="text-sm text-primary/80">Նոր այսօր</p>
              <p className="mt-1 text-3xl font-semibold text-primary">{dashboard.newToday}</p>
            </Card>
            <Card className="border-primary-light bg-primary-light">
              <p className="text-sm text-primary/80">Նոր այս շաբաթ</p>
              <p className="mt-1 text-3xl font-semibold text-primary">{dashboard.newThisWeek}</p>
            </Card>
            <Card className="col-span-2 sm:col-span-2">
              <p className="text-sm text-ink-muted">Ընթացքի մեջ</p>
              <p className="mt-1 text-3xl font-semibold text-ink">
                {(dashboard.countsByStatus.confirmed ?? 0) + (dashboard.countsByStatus.in_progress ?? 0)}
              </p>
            </Card>
          </div>

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
            {LEAD_STATUSES.map((status) => (
              <Card key={status} className="p-4">
                <p className="text-xs text-ink-muted">{LEAD_STATUS_LABELS[status]}</p>
                <p className="mt-1 text-xl font-semibold text-ink">{dashboard.countsByStatus[status] ?? 0}</p>
              </Card>
            ))}
          </div>

          <div className="space-y-3">
            <h2 className="text-base font-semibold text-ink">Վերջին պատվերները</h2>
            <TableWrap>
              <Thead>
                <Th>Անուն</Th>
                <Th>Հեռախոս</Th>
                <Th>Կարգավիճակ</Th>
                <Th>Ամսաթիվ</Th>
              </Thead>
              <tbody>
                {dashboard.recentLeads.map((lead) => (
                  <Tr key={lead.id}>
                    <Td className="font-medium">{lead.name}</Td>
                    <Td className="text-ink-muted">{lead.phone}</Td>
                    <Td>
                      <Badge tone={LEAD_STATUS_TONES[lead.status]}>{LEAD_STATUS_LABELS[lead.status]}</Badge>
                    </Td>
                    <Td className="text-ink-muted">
                      {lead.createdAt ? new Date(lead.createdAt).toLocaleDateString("hy-AM") : "—"}
                    </Td>
                  </Tr>
                ))}
                {dashboard.recentLeads.length === 0 ? <EmptyRow colSpan={4}>Պատվերներ դեռ չկան։</EmptyRow> : null}
              </tbody>
            </TableWrap>
          </div>
        </>
      ) : (
        <p className="text-sm text-danger">Չհաջողվեց բեռնել տվյալները։</p>
      )}
    </div>
  );
}

async function TablesHome() {
  const tables = await getDataTables();
  // One card per section, however many tables (a file a day is thirty a month).
  const sections = (Object.keys(SECTIONS) as DataTableSection[])
    .map((section) => ({ section, tables: tables.filter((t) => t.section === section) }))
    .filter((s) => s.tables.length > 0);

  return (
    <div className="space-y-6">
      <PageHeader title="Վահանակ" />
      {sections.length === 0 ? (
        <EmptyCard>Աղյուսակներ դեռ չկան։ Ստեղծեք առաջինը «Վաճառքներ» բաժնում։</EmptyCard>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {sections.map(({ section, tables }) => {
            const latest = [...tables].sort((a, b) => (b.lastAt ?? "").localeCompare(a.lastAt ?? ""))[0];
            const amount = tables.some((t) => t.amountColumn) ? tables.reduce((sum, t) => sum + (t.amountTotal ?? 0), 0) : null;
            return (
              <Link key={section} href={tablesPath(section)}>
                <Card className="h-full transition-colors hover:border-primary">
                  <p className="text-xs text-ink-faint">{tables.length} աղյուսակ</p>
                  <p className="mt-1 font-medium text-ink">{SECTIONS[section].label}</p>
                  <p className="mt-3 text-3xl font-semibold text-ink">
                    {tables.reduce((sum, t) => sum + (t.rowsCount ?? 0), 0)}
                  </p>
                  <p className="text-xs text-ink-muted">
                    տող{amount !== null ? ` · ${formatMoney(amount)}` : ""}
                    {latest?.lastAt ? ` · վերջինը՝ ${formatMoment(latest.lastAt)}` : ""}
                  </p>
                </Card>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}

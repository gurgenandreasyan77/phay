import Link from "next/link";
import { requireAdmin } from "@/lib/adminAuth";
import { getDashboard } from "@/lib/adminApi";
import { LEAD_STATUS_LABELS, LEAD_STATUS_TONES, LEAD_STATUSES } from "./_components/leadStatus";
import { Badge, buttonClasses, Card, EmptyRow, PageHeader, TableWrap, Th, Thead, Td, Tr } from "./_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminDashboardPage() {
  await requireAdmin();
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

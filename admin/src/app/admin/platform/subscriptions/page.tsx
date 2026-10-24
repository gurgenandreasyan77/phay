import Link from "next/link";
import { requireSuperAdmin } from "@/lib/adminAuth";
import { getSubscriptionsAdmin, type SubscriptionStatus } from "@/lib/adminApi";
import { BusinessLogo } from "../../_components/BusinessLogo";
import { EmptyRow, PageHeader, TableWrap, Td, Th, Thead, Tr, cn } from "@/ui";
import { STATUS_LABELS, StatusBadge, amd, formatDay, subscriptionLine } from "../../_components/subscription/shared";

export const dynamic = "force-dynamic";

const FILTERS: (SubscriptionStatus | null)[] = [null, "trial", "active", "free", "canceling", "expired", "unlimited"];

/** Every business's subscription (super admin): who pays what, until when. */
export default async function PlatformSubscriptionsPage({
  searchParams,
}: {
  searchParams: Promise<{ status?: string }>;
}) {
  await requireSuperAdmin();
  const { status: requested } = await searchParams;
  const status = FILTERS.find((f) => f === requested) ?? null;
  const rows = await getSubscriptionsAdmin(status ?? undefined);
  const warnDays = rows[0]?.subscription.warnDays ?? 10;
  const monthly = rows
    .filter((row) => ["active", "canceling"].includes(row.subscription.status))
    .reduce((sum, row) => sum + row.subscription.pricing.total, 0);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Բաժանորդագրություններ"
        description={`Բոլոր բիզնեսների բաժանորդագրությունները։ Բաժանորդագրությունն ավարտվելուց ${warnDays} օր առաջ բիզնեսը նախազգուշացում է ստանում չատում, իսկ ավարտից հետո ադմինը փակվում է՝ առանց տվյալները ջնջելու։`}
      />

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-2">
          {FILTERS.map((filter) => (
            <Link
              key={filter ?? "all"}
              href={filter ? `/admin/platform/subscriptions?status=${filter}` : "/admin/platform/subscriptions"}
              className={cn(
                "rounded-full border px-3 py-1 text-xs font-medium",
                filter === status ? "border-primary bg-primary-light text-primary" : "border-border bg-surface text-ink-muted hover:text-ink",
              )}
            >
              {filter ? STATUS_LABELS[filter] : "Բոլորը"}
            </Link>
          ))}
        </div>
        {status === null ? (
          <p className="text-sm text-ink-muted">
            Վճարող բիզնեսների ամսական գումարը՝ <span className="font-semibold text-ink">{amd(monthly)}</span>
          </p>
        ) : null}
      </div>

      <TableWrap>
        <Thead>
          <Th>Բիզնես</Th>
          <Th>Կարգավիճակ</Th>
          <Th>Մինչև</Th>
          <Th>Փաթեթ</Th>
          <Th>Ամսական</Th>
          <Th>Վերջին վճարում</Th>
        </Thead>
        <tbody>
          {rows.map(({ tenant, subscription, lastPayment }) => (
            <Tr key={tenant.id}>
              <Td>
                <Link href={`/admin/platform/subscriptions/${tenant.id}`} className="flex items-center gap-2 font-medium text-ink hover:text-primary">
                  <BusinessLogo name={tenant.name} logo={tenant.logo} size="sm" />
                  {tenant.name}
                </Link>
              </Td>
              <Td>
                <StatusBadge status={subscription.status} />
              </Td>
              <Td className={cn("text-ink-muted", subscription.ending && "font-medium text-warning")}>{subscriptionLine(subscription)}</Td>
              <Td className="text-ink-muted">{subscription.plan?.name ?? "—"}</Td>
              <Td className="font-medium">{amd(subscription.pricing.total)}</Td>
              <Td className="text-ink-muted">
                {lastPayment ? `${amd(lastPayment.amount)} · ${formatDay(lastPayment.createdAt)}` : "—"}
              </Td>
            </Tr>
          ))}
          {rows.length === 0 ? <EmptyRow colSpan={6}>Այս կարգավիճակով բիզնեսներ չկան։</EmptyRow> : null}
        </tbody>
      </TableWrap>
    </div>
  );
}

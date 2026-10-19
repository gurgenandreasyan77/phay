import { requireAdmin } from "@/lib/adminAuth";
import { getSubscription } from "@/lib/adminApi";
import { can, isPlatformStaff } from "@/lib/roles";
import { Card, EmptyCard, EmptyRow, PageHeader, SectionCard, TableWrap, Td, Th, Thead, Tr } from "../_components/ui";
import { PriceBreakdown, StatusBadge, amd, formatDay, subscriptionLine } from "../_components/subscription/shared";
import { RequestButton } from "./RequestButton";

export const dynamic = "force-dynamic";

/**
 * The business's subscription: where it stands, what it pays a month (its
 * plan plus the modules it has on) and its payments. Open even after the
 * subscription ended: the owner asks to renew from here, in the chat.
 */
export default async function SubscriptionPage() {
  const admin = await requireAdmin({ allowLocked: true });
  const data = await getSubscription();

  if (!data) {
    return (
      <div className="space-y-6">
        <PageHeader title="Բաժանորդագրություն" />
        <EmptyCard>Չհաջողվեց բեռնել բաժանորդագրությունը։</EmptyCard>
      </div>
    );
  }

  const { subscription, payments, plans } = data;
  // Asking goes to the chat, which the owner and admin have.
  const canAsk = can(admin, "settings") && !isPlatformStaff(admin);
  // Modules on sale: the platform gave them a price (or a plan includes them).
  const otherModules = subscription.pricing.modules.filter((m) => !m.active && (m.price > 0 || m.included));

  return (
    <div className="space-y-8">
      <PageHeader
        title="Բաժանորդագրություն"
        description="Ամսական վճարը կազմված է փաթեթի գնից և ձեր միացված մոդուլների գներից։ Բաժանորդագրության ավարտից հետո ձեր տվյալները չեն ջնջվում։"
      />

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-base font-semibold text-ink">Կարգավիճակ</h2>
            <StatusBadge status={subscription.status} />
          </div>
          <p className="text-sm text-ink">{subscriptionLine(subscription)}</p>
          {subscription.status === "expired" ? (
            <p className="text-sm text-ink-muted">
              Ադմինը ժամանակավորապես փակ է, բայց ձեր բոլոր տվյալները պահպանված են։ Վճարելուց հետո ամեն ինչ կվերադառնա։
            </p>
          ) : null}
          {canAsk && subscription.status !== "unlimited" ? (
            <RequestButton body={{ kind: "renew" }} label="Երկարացնել բաժանորդագրությունը" primary />
          ) : null}
        </Card>

        <Card className="space-y-4">
          <h2 className="text-base font-semibold text-ink">Ամսական վճար</h2>
          <PriceBreakdown subscription={subscription} />
        </Card>
      </div>

      {otherModules.length > 0 ? (
        <SectionCard title="Այլ մոդուլներ" description="Կարող եք միացնել դրանք. գինը կավելանա ամսական վճարին։">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {otherModules.map((module) => (
              <Card key={module.key} className="flex flex-col gap-3">
                <div className="flex items-start justify-between gap-3">
                  <p className="font-medium text-ink">{module.label}</p>
                  <p className="text-sm text-ink-muted">{module.included ? "Ներառված է" : `+ ${amd(module.price)}/ամիս`}</p>
                </div>
                {canAsk ? <RequestButton body={{ kind: "module", module: module.key }} label="Միացնել" /> : null}
              </Card>
            ))}
          </div>
        </SectionCard>
      ) : null}

      {plans.length > 1 ? (
        <SectionCard title="Փաթեթներ">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {plans.map((plan) => {
              const current = plan.id === subscription.plan?.id;
              return (
                <Card key={plan.id} className="flex flex-col gap-2">
                  <div className="flex items-start justify-between gap-3">
                    <p className="font-medium text-ink">{plan.name}</p>
                    <p className="text-sm font-semibold text-ink">{amd(plan.price)}/ամիս</p>
                  </div>
                  {plan.description ? <p className="text-sm text-ink-muted">{plan.description}</p> : null}
                  {current ? (
                    <p className="text-sm font-medium text-primary">Ձեր փաթեթը</p>
                  ) : canAsk ? (
                    <RequestButton body={{ kind: "plan", plan_id: plan.id }} label="Անցնել այս փաթեթին" />
                  ) : null}
                </Card>
              );
            })}
          </div>
        </SectionCard>
      ) : null}

      <SectionCard title="Վճարումներ">
        <TableWrap>
          <Thead>
            <Th>Ամսաթիվ</Th>
            <Th>Գումար</Th>
            <Th>Ժամանակահատված</Th>
          </Thead>
          <tbody>
            {payments.map((payment) => (
              <Tr key={payment.id}>
                <Td>{formatDay(payment.createdAt)}</Td>
                <Td className="font-medium">{amd(payment.amount)}</Td>
                <Td className="text-ink-muted">
                  {formatDay(payment.periodFrom)} – {formatDay(payment.periodTo)}
                </Td>
              </Tr>
            ))}
            {payments.length === 0 ? <EmptyRow colSpan={3}>Վճարումներ դեռ չկան։</EmptyRow> : null}
          </tbody>
        </TableWrap>
      </SectionCard>
    </div>
  );
}

import { requireAdmin } from "@/lib/adminAuth";
import { getSubscription } from "@/lib/adminApi";
import { can, isPlatformStaff } from "@/lib/roles";
import type { CSSProperties } from "react";
import { Badge, Card, EmptyCard, EmptyRow, PageHeader, SectionCard, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import { PriceBreakdown, StatusBadge, amd, formatDay, subscriptionLine } from "../_components/subscription/shared";
import { AgentTeamBilling } from "../_components/agents/AgentTeamBilling";
import { BalanceCard } from "../_components/balance/BalanceCard";
import { BalanceHistory } from "../_components/balance/BalanceHistory";
import { PayFromBalance } from "../_components/balance/PayFromBalance";
import { DaysRing } from "./DaysRing";
import { ModuleCard } from "./ModuleCard";
import { PremiumTeaser } from "./PremiumTeaser";
import { RequestButton } from "./RequestButton";
import styles from "./subscription.module.css";

export const dynamic = "force-dynamic";

/**
 * The business's subscription: where it stands, what it pays a month (its
 * plan plus the modules it has on), what premium will add, the modules it
 * can turn on, its AI agents each on its own term (trial, paid months, one
 * common billing day) and its payments. The owner and the admin pay from
 * the balance (topped up by Phay when a receipt comes in the chat) or ask
 * in the chat. Open even after the subscription ended: paying from the
 * balance brings the admin back.
 */
export default async function SubscriptionPage({ searchParams }: { searchParams: Promise<{ agent?: string }> }) {
  const admin = await requireAdmin({ allowLocked: true });
  const data = await getSubscription();
  // From an agent's card on the team page: open paying for it.
  const agentParam = (await searchParams).agent ?? null;

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
  // Paying from the balance: the same people, once the API sent it.
  const balance = canAsk ? (data.balance ?? null) : null;
  const paidUntil = formatDay(subscription.paidUntil);
  const currentPrice = subscription.plan?.price ?? 0;
  // A real bigger plan replaces the premium preview once the platform adds one.
  const upgrades = plans.filter((plan) => plan.id !== subscription.plan?.id && plan.price > currentPrice);
  // Modules a business can have: on already, priced by the platform, or inside a plan.
  const modules = subscription.pricing.modules.filter((m) => m.kind !== "agent" && (m.active || m.price > 0 || m.included));

  return (
    <div className="space-y-8">
      <PageHeader
        title="Բաժանորդագրություն"
        description="Ամսական վճարը կազմված է փաթեթի գնից և ձեր միացված մոդուլների գներից։ Բաժանորդագրության ավարտից հետո ձեր տվյալները չեն ջնջվում։"
      />

      {balance ? <BalanceCard balance={balance} /> : null}

      <div className="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
        <Card className={`${styles.hero} ${styles.rise} flex flex-col gap-5 sm:flex-row sm:items-center`}>
          <DaysRing subscription={subscription} />
          <div className="min-w-0 flex-1 space-y-3">
            <div className="flex flex-wrap items-center gap-2">
              <StatusBadge status={subscription.status} />
              {subscription.plan ? (
                <span className="text-xs font-medium text-ink-muted">Փաթեթ «{subscription.plan.name}»</span>
              ) : null}
            </div>
            <p className="text-lg font-semibold text-ink">{subscriptionLine(subscription)}</p>
            {subscription.status === "expired" ? (
              <p className="text-sm text-ink-muted">
                Ադմինը ժամանակավորապես փակ է, բայց ձեր բոլոր տվյալները պահպանված են։ Վճարելուց հետո ամեն ինչ կվերադառնա։
              </p>
            ) : null}
            {balance && subscription.status !== "unlimited" && subscription.pricing.total > 0 ? (
              <PayFromBalance
                purchase={{ action: "subscription", months: 1 }}
                label="Երկարացնել հաշվեկշռից"
                primary
                confirm={{
                  title: "Երկարացնե՞լ մեկ ամսով հաշվեկշռից",
                  description: "Գումարը կգանձվի հաշվեկշռից, և ժամկետը կերկարաձգվի ընթացիկ ավարտի օրից (ավարտվածը՝ այսօրվանից)։",
                  details: [
                    { label: "Կգանձվի", value: amd(subscription.pricing.total) },
                    { label: "Հաշվեկշռում", value: amd(balance.amount) },
                    {
                      label: subscription.status === "expired" ? "Ավարտվել է" : "Գործում է մինչև",
                      value: formatDay(subscription.endsAt),
                    },
                  ],
                  confirmLabel: "Վճարել",
                }}
              />
            ) : null}
            {canAsk && subscription.status !== "unlimited" ? (
              <RequestButton
                body={{ kind: "renew" }}
                label={balance ? "Գրել չատում" : "Երկարացնել բաժանորդագրությունը"}
                primary={!balance}
                confirm={{
                  title: "Երկարացնե՞լ բաժանորդագրությունը",
                  description:
                    "Հայտը կուղարկվի Phay-ի թիմին, որը չատում կգրի վճարման մանրամասները։ Վճարելուց հետո ժամկետը կերկարաձգվի ընթացիկ ավարտի օրից։",
                  details: [
                    {
                      label: "Ամսական վճար",
                      value: amd(subscription.pricing.total),
                    },
                    {
                      label: subscription.status === "expired" ? "Ավարտվել է" : "Գործում է մինչև",
                      value: formatDay(subscription.endsAt),
                    },
                  ],
                  confirmLabel: "Այո, երկարացնել",
                }}
              />
            ) : null}
          </div>
        </Card>

        <Card className={`${styles.rise} space-y-4`} style={{ "--i": 1 } as CSSProperties}>
          <div className="flex items-baseline justify-between gap-3">
            <h2 className="text-base font-semibold text-ink">Ամսական վճար</h2>
            <p className="text-2xl font-semibold tracking-tight text-ink">{amd(subscription.pricing.total)}</p>
          </div>
          <PriceBreakdown subscription={subscription} />
        </Card>
      </div>

      {!subscription.premium && upgrades.length === 0 ? (
        <PremiumTeaser assistant={data.assistant ?? { used: 0, limit: 0 }} canAsk={canAsk} />
      ) : null}

      {modules.length > 0 ? (
        <div id="modules" className="scroll-mt-20">
          <SectionCard title="Մոդուլներ" description="Միացրեք ձեզ անհրաժեշտ մոդուլները. դրանց գինը կավելանա ամսական վճարին։">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {modules.map((module, index) => (
                <ModuleCard
                  key={module.key}
                  module={module}
                  monthly={subscription.pricing.total}
                  canAsk={canAsk}
                  index={index}
                  payFromBalance={
                    balance && ["active", "trial", "free", "unlimited"].includes(subscription.status)
                      ? { paidUntil: subscription.status === "active" ? paidUntil : null }
                      : null
                  }
                />
              ))}
            </div>
          </SectionCard>
        </div>
      ) : null}

      {data.agentBilling ? <AgentTeamBilling billing={data.agentBilling} canAsk={canAsk} openAgent={agentParam} balance={balance?.amount ?? null} /> : null}

      {plans.length > 1 ? (
        <SectionCard title="Փաթեթներ">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {plans.map((plan, index) => {
              const current = plan.id === subscription.plan?.id;
              return (
                <Card
                  key={plan.id}
                  className={`${styles.rise} flex flex-col gap-3 ${current ? "border-primary ring-1 ring-primary" : ""}`}
                  style={{ "--i": index } as CSSProperties}
                >
                  <div className="flex items-start justify-between gap-3">
                    <p className="font-semibold text-ink">{plan.name}</p>
                    {current ? <Badge tone="primary">Ձեր փաթեթը</Badge> : null}
                  </div>
                  <p className="text-2xl font-semibold tracking-tight text-ink">
                    {amd(plan.price)}
                    <span className="text-sm font-normal text-ink-muted">/ամիս</span>
                  </p>
                  {plan.description ? <p className="text-sm text-ink-muted">{plan.description}</p> : null}
                  {!current && balance && plan.price > currentPrice && subscription.status !== "canceling" ? (
                    <div className="mt-auto">
                      <PayFromBalance
                        purchase={{ action: "plan", plan_id: plan.id }}
                        label="Անցնել հաշվեկշռից"
                        confirm={{
                          title: `Անցնե՞լ «${plan.name}» փաթեթին`,
                          description:
                            subscription.status === "active"
                              ? `Հաշվեկշռից կգանձվի ամսական գների տարբերությունը մինչև ${paidUntil}, հետո ամսական վճարը կլինի նոր փաթեթով։`
                              : subscription.status === "expired"
                                ? "Հաշվեկշռից կգանձվի նոր փաթեթի առաջին ամիսը, և ադմինը կբացվի։"
                                : "Փաթեթը կփոխվի հիմա. վճարը կգանձվի ընթացիկ ժամկետի ավարտին։",
                          details: [
                            ...(subscription.plan ? [{ label: `Հիմա՝ «${subscription.plan.name}»`, value: `${amd(subscription.plan.price)}/ամիս` }] : []),
                            { label: `Նոր փաթեթ՝ «${plan.name}»`, value: `${amd(plan.price)}/ամիս` },
                            { label: "Հաշվեկշռում", value: amd(balance.amount) },
                          ],
                          confirmLabel: "Այո, անցնել",
                        }}
                      />
                    </div>
                  ) : !current && canAsk ? (
                    <div className="mt-auto">
                      <RequestButton
                        body={{ kind: "plan", plan_id: plan.id }}
                        label="Անցնել այս փաթեթին"
                        confirm={{
                          title: `Անցնե՞լ «${plan.name}» փաթեթին`,
                          description: "Հայտը կուղարկվի Phay-ի թիմին, որը չատում կհաստատի անցումը։",
                          details: [
                            ...(subscription.plan
                              ? [
                                  {
                                    label: `Հիմա՝ «${subscription.plan.name}»`,
                                    value: `${amd(subscription.plan.price)}/ամիս`,
                                  },
                                ]
                              : []),
                            {
                              label: `Նոր փաթեթ՝ «${plan.name}»`,
                              value: `${amd(plan.price)}/ամիս`,
                            },
                          ],
                          confirmLabel: "Այո, անցնել",
                        }}
                      />
                    </div>
                  ) : null}
                </Card>
              );
            })}
          </div>
        </SectionCard>
      ) : null}

      {balance ? (
        <div id="balance" className="scroll-mt-20">
          <SectionCard title="Հաշվեկշռի պատմություն" description="Բոլոր համալրումները և գանձումները, ամենավերջինը՝ վերևում։">
            <BalanceHistory transactions={balance.transactions} />
          </SectionCard>
        </div>
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

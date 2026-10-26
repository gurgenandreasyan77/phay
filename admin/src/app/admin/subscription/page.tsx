import { requireAdmin } from "@/lib/adminAuth";
import { getSubscription } from "@/lib/adminApi";
import { can, isPlatformStaff } from "@/lib/roles";
import type { CSSProperties } from "react";
import { Badge, Card, EmptyCard, EmptyRow, PageHeader, SectionCard, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import Link from "next/link";
import { PriceBreakdown, StatusBadge, amd, formatDay, subscriptionLine } from "../_components/subscription/shared";
import { AgentAvatar } from "../_components/agents/AgentAvatar";
import { DaysRing } from "./DaysRing";
import { ModuleCard } from "./ModuleCard";
import { PremiumTeaser } from "./PremiumTeaser";
import { RequestButton } from "./RequestButton";
import styles from "./subscription.module.css";

export const dynamic = "force-dynamic";

/**
 * The business's subscription: where it stands, what it pays a month (its
 * plan plus the modules it has on), what premium will add, the modules it
 * can turn on and its payments. Open even after the
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
  const currentPrice = subscription.plan?.price ?? 0;
  // A real bigger plan replaces the premium preview once the platform adds one.
  const upgrades = plans.filter((plan) => plan.id !== subscription.plan?.id && plan.price > currentPrice);
  // Modules a business can have: on already, priced by the platform, or inside a plan.
  const modules = subscription.pricing.modules.filter((m) => m.kind !== "agent" && (m.active || m.price > 0 || m.included));
  // AI agents are billed like modules but shown as the team they are.
  const agents = subscription.pricing.modules.filter((m) => m.kind === "agent");

  return (
    <div className="space-y-8">
      <PageHeader
        title="Բաժանորդագրություն"
        description="Ամսական վճարը կազմված է փաթեթի գնից և ձեր միացված մոդուլների գներից։ Բաժանորդագրության ավարտից հետո ձեր տվյալները չեն ջնջվում։"
      />

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
            {canAsk && subscription.status !== "unlimited" ? (
              <RequestButton
                body={{ kind: "renew" }}
                label="Երկարացնել բաժանորդագրությունը"
                primary
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
                <ModuleCard key={module.key} module={module} monthly={subscription.pricing.total} canAsk={canAsk} index={index} />
              ))}
            </div>
          </SectionCard>
        </div>
      ) : null}

      {agents.length > 0 ? (
        <div id="agents" className="scroll-mt-20">
          <SectionCard
            title="AI աշխատակիցներ"
            description="Վիրտուալ թիմ, որը սովորում է ձեր բիզնեսը. նրանց գինը կավելանա ամսական վճարին, եթե ներառված չեն փաթեթում։"
            actions={
              <Link href="/admin/employees?tab=agents" className="text-sm font-medium text-primary hover:underline">
                Ծանոթանալ թիմին
              </Link>
            }
          >
            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {agents.map((agent) => {
                const key = agent.key.replace(/^agent:/, "");
                return (
                  <li key={agent.key} className="flex items-center gap-3 rounded-lg border border-border p-3">
                    <AgentAvatar agentKey={key} size={52} active={agent.active} />
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium text-ink">{agent.label.replace(/^AI · /, "")}</p>
                      <p className="text-xs text-ink-muted">
                        {agent.included ? "Ներառված է փաթեթում" : `${amd(agent.price)} / ամիս`}
                      </p>
                    </div>
                    {agent.active ? (
                      <Badge tone="success">Թիմում է</Badge>
                    ) : canAsk ? (
                      <RequestButton
                        body={{ kind: "agent", agent: key }}
                        label="Ավելացնել"
                        confirm={{
                          title: `Ավելացնե՞լ «${agent.label.replace(/^AI · /, "")}» AI աշխատակցին ձեր թիմին`,
                          description: agent.included
                            ? "Հայտը կուղարկվի Phay-ի թիմին։ Այս աշխատակիցը ներառված է ձեր փաթեթում, ամսական վճարը չի փոխվի։"
                            : "Հայտը կուղարկվի Phay-ի թիմին։ Միացնելուց հետո նրա գինը կավելանա ամսական վճարին։",
                          details: agent.included
                            ? [
                                {
                                  label: "Ամսական վճար",
                                  value: amd(subscription.pricing.total),
                                },
                              ]
                            : [
                                {
                                  label: "Գինը",
                                  value: `+ ${amd(agent.price)}/ամիս`,
                                },
                                {
                                  label: "Նոր ամսական վճարը",
                                  value: amd(subscription.pricing.total + agent.price),
                                },
                              ],
                          confirmLabel: "Ուղարկել հայտը",
                        }}
                      />
                    ) : null}
                  </li>
                );
              })}
            </ul>
          </SectionCard>
        </div>
      ) : null}

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
                  {!current && canAsk ? (
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

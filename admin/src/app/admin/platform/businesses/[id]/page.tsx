import Link from "next/link";
import { notFound } from "next/navigation";
import type { ReactNode } from "react";
import { Lock } from "lucide-react";
import { requirePlatformStaff } from "@/lib/adminAuth";
import { getBusinessOverviewAdmin, type BusinessOverviewDto } from "@/lib/adminApi";
import { BUSINESS_ROLES, BUSINESS_ROLE_LABELS, isSuperAdmin } from "@/lib/roles";
import { Badge, Card, PageHeader, buttonClasses, cn } from "@/ui";
import { BusinessLogo } from "../../../_components/BusinessLogo";
import { AgentAvatar } from "../../../_components/agents/AgentAvatar";
import { CONTACT_LABELS } from "../../contacts";
import { StatusBadge, amd, daysLeftLabel, formatDay, subscriptionLine } from "../../../_components/subscription/shared";

export const dynamic = "force-dynamic";

/**
 * One business as the platform team sees it: how long and how actively it
 * uses Phay, its subscription, modules, AI agents and usage, how many people
 * it has, and the chat with it. Its own data (orders, sales, money,
 * conversations) stays closed: the API doesn't give it to platform staff.
 */
export default async function PlatformBusinessPage({ params }: { params: Promise<{ id: string }> }) {
  const admin = await requirePlatformStaff();
  const { id } = await params;
  const data = await getBusinessOverviewAdmin(Number(id));
  if (!data) notFound();

  const superAdmin = isSuperAdmin(admin);
  const { tenant, activity, subscription } = data;

  return (
    <div className="space-y-6">
      <PageHeader
        title={tenant.name}
        description={
          <span className="flex flex-wrap items-center gap-2">
            <BusinessLogo name={tenant.name} logo={tenant.logo} />
            <span className="font-mono text-xs">{tenant.slug}</span>
            {tenant.status === "active" ? <Badge tone="success">Ակտիվ</Badge> : <Badge tone="warning">Կասեցված</Badge>}
            <StatusBadge status={subscription.status} />
          </span>
        }
        actions={
          <Link href="/admin/platform" className="text-sm text-ink-muted hover:text-ink">
            ← Բիզնեսներ
          </Link>
        }
      />

      <div className="flex items-start gap-3 rounded-lg border border-border bg-surface-secondary px-4 py-3 text-sm text-ink-muted">
        <Lock className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={1.75} />
        <p>
          Բիզնեսի պատվերները, վաճառքները, եկամուտները, ծախսերը և զրույցները փակ են պլատֆորմի համար։ Այստեղ միայն կոնտակտներն են,
          ակտիվությունը, բաժանորդագրությունը և մոդուլները։
        </p>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat label="Պլատֆորմում" value={activity.daysOnPlatform === null ? "—" : `${activity.daysOnPlatform} օր`}>
          {formatDay(activity.createdAt)}-ից
        </Stat>
        <Stat label="Ակտիվ օրեր (30 օր)" value={`${activity.activeDaysRecent} / 30`}>
          Ընդամենը՝ {activity.activeDays} օր
        </Stat>
        <Stat label="Վերջին մուտք" value={activity.lastSeenAt ? formatDay(activity.lastSeenAt) : "—"}>
          {activity.lastSeenAt ? sinceLabel(activity.lastSeenAt) : "Դեռ չի մտել"}
        </Stat>
        <Stat label="Մնացել է" value={daysLeftLabel(subscription.daysLeft)}>
          {subscription.endsAt ? `մինչև ${formatDay(subscription.endsAt)}` : "Առանց ավարտի"}
        </Stat>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <ActivityCard activity={activity} />

        <Card className="space-y-4">
          <CardTitle
            title="Բաժանորդագրություն"
            action={
              superAdmin ? (
                <Link href={`/admin/platform/subscriptions/${tenant.id}`} className={buttonClasses("secondary", "sm")}>
                  Կառավարել
                </Link>
              ) : null
            }
          />
          <p className="text-sm text-ink">{subscriptionLine(subscription)}</p>
          <dl className="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
            <Row label="Փաթեթ" value={subscription.unlimited ? "Անժամկետ" : (subscription.plan?.name ?? "—")} />
            <Row label="Ամսական" value={amd(subscription.pricing.total)} />
            <Row label="Փորձաշրջան մինչև" value={formatDay(subscription.trialEndsAt)} />
            <Row label="Վճարված մինչև" value={formatDay(subscription.paidUntil)} />
            <Row label="Անվճար մինչև" value={formatDay(subscription.freeUntil)} />
            {subscription.cancelAt ? <Row label="Անջատվում է" value={formatDay(subscription.cancelAt)} /> : null}
          </dl>
          {data.payments ? <Payments payments={data.payments} /> : null}
        </Card>

        <ContactsCard tenant={tenant} />

        <Card className="space-y-4">
          <CardTitle title="Կապ բիզնեսի հետ" />
          <dl className="grid grid-cols-2 gap-3 text-sm">
            <Row label="Չկարդացված" value={String(data.support.unread)} />
            <Row label="Վերջին հաղորդագրություն" value={formatDay(data.support.lastMessageAt)} />
          </dl>
          <Link
            href={`/admin/platform/support?tenant=${tenant.id}`}
            className={buttonClasses("primary", "sm", "w-full sm:w-auto")}
          >
            Բացել զրույցը
          </Link>
        </Card>

        <Card className="space-y-4">
          <CardTitle title="Մոդուլներ և AI թիմ" />
          <ul className="flex flex-wrap gap-2">
            {data.modules.map((module) => (
              <li key={module.key}>
                <Badge tone={module.active ? "success" : "default"}>
                  {module.label} · {module.active ? "միացված" : "անջատված"}
                </Badge>
              </li>
            ))}
          </ul>
          <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            {data.agents.map((agent, index) => (
              <li
                key={agent.key}
                className={cn(
                  "flex items-center gap-3 rounded-lg border border-border px-3 py-2",
                  agent.active ? "bg-surface" : "bg-surface-secondary",
                )}
              >
                <AgentAvatar agentKey={agent.key} size={36} active={agent.active} delay={-index * 0.9} title={agent.label} />
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-medium text-ink">{agent.label}</span>
                  <span className="text-xs text-ink-muted">{agent.active ? "Թիմում է" : "Միացված չէ"}</span>
                </span>
              </li>
            ))}
          </ul>
        </Card>

        <AssistantCard assistant={data.assistant} />

        <Card className="space-y-4">
          <CardTitle title="Մարդիկ" />
          <dl className="grid grid-cols-2 gap-3 text-sm">
            <Row label="Մուտքեր" value={`${data.people.logins} (ակտիվ՝ ${data.people.activated})`} />
            <Row label="Աշխատակիցներ" value={String(data.people.employees)} />
            {BUSINESS_ROLES.map((role) => (
              <Row key={role} label={BUSINESS_ROLE_LABELS[role]} value={String(data.people.byRole[role] ?? 0)} />
            ))}
            <Row label="Կայքեր" value={String(data.sites)} />
          </dl>
          <p className="text-xs text-ink-muted">Միայն քանակներ՝ առանց անունների և տվյալների։</p>
        </Card>
      </div>
    </div>
  );
}

function ContactsCard({ tenant }: { tenant: BusinessOverviewDto["tenant"] }) {
  const address = tenant.address?.hy || tenant.address?.ru || tenant.address?.en || null;
  const contacts = tenant.contacts ?? [];

  return (
    <Card className="space-y-4">
      <CardTitle title="Կոնտակտներ" />
      {contacts.length === 0 && !address ? (
        <p className="text-sm text-ink-muted">Բիզնեսը դեռ չի լրացրել կոնտակտները։</p>
      ) : (
        <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
          {contacts.map((contact, i) => (
            <div key={`${contact.type}-${i}`} className="min-w-0">
              <dt className="text-xs text-ink-muted">{contact.label || CONTACT_LABELS[contact.type] || contact.type}</dt>
              <dd className="break-words font-medium text-ink">{contact.value}</dd>
            </div>
          ))}
          {address ? (
            <div className="min-w-0 sm:col-span-2">
              <dt className="text-xs text-ink-muted">Հասցե</dt>
              <dd className="break-words font-medium text-ink">{address}</dd>
            </div>
          ) : null}
        </dl>
      )}
    </Card>
  );
}

function Stat({ label, value, children }: { label: string; value: string; children?: ReactNode }) {
  return (
    <Card className="space-y-1 p-4">
      <p className="text-xs font-medium text-ink-muted">{label}</p>
      <p className="text-lg font-semibold text-ink sm:text-xl">{value}</p>
      {children ? <p className="truncate text-xs text-ink-muted">{children}</p> : null}
    </Card>
  );
}

function CardTitle({ title, action }: { title: string; action?: ReactNode }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h2 className="text-base font-semibold text-ink">{title}</h2>
      {action}
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs text-ink-muted">{label}</dt>
      <dd className="truncate font-medium text-ink">{value}</dd>
    </div>
  );
}

/** The last 30 days as squares: filled when the business's people worked that day. */
function ActivityCard({ activity }: { activity: BusinessOverviewDto["activity"] }) {
  return (
    <Card className="space-y-4">
      <CardTitle title="Ակտիվություն" />
      <ol className="grid grid-cols-10 gap-1.5 sm:grid-cols-15" aria-label="Վերջին 30 օրը">
        {activity.recent.map((day) => (
          <li
            key={day.date}
            title={`${formatDay(`${day.date}T12:00:00Z`)} · ${day.active ? "աշխատել են" : "մուտք չի եղել"}`}
            className={cn("aspect-square rounded-sm", day.active ? "bg-primary" : "bg-surface-secondary")}
          />
        ))}
      </ol>
      <dl className="grid grid-cols-2 gap-3 text-sm">
        <Row label="Առաջին ակտիվ օր" value={activity.firstActiveDay ? formatDay(`${activity.firstActiveDay}T12:00:00Z`) : "—"} />
        <Row label="Վերջին ակտիվ օր" value={activity.lastActiveDay ? formatDay(`${activity.lastActiveDay}T12:00:00Z`) : "—"} />
      </dl>
      <p className="text-xs text-ink-muted">Ակտիվ օր՝ բիզնեսի աշխատակիցներից որևէ մեկը մտել է ադմին։</p>
    </Card>
  );
}

function Payments({ payments }: { payments: NonNullable<BusinessOverviewDto["payments"]> }) {
  return (
    <div className="space-y-2 border-t border-border pt-4">
      <p className="text-sm text-ink">
        Վճարումներ՝ {payments.count}, ընդամենը՝ <span className="font-medium">{amd(payments.total)}</span>
      </p>
      {payments.latest.length > 0 ? (
        <ul className="divide-y divide-border text-sm">
          {payments.latest.map((payment) => (
            <li key={payment.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <span className="text-ink-muted">{formatDay(payment.createdAt)}</span>
              <span className="text-ink">
                {amd(payment.amount)} · {payment.months} ամիս
              </span>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}

function AssistantCard({ assistant }: { assistant: BusinessOverviewDto["assistant"] }) {
  const percent = assistant.limit ? Math.min(100, Math.round((assistant.used / assistant.limit) * 100)) : 0;

  return (
    <Card className="space-y-4">
      <CardTitle title="AI օգնական" />
      <div className="space-y-2">
        <p className="text-sm text-ink">
          Այս ամիս՝ <span className="font-semibold">{assistant.used}</span>
          {assistant.unlimited ? " հարց (առանց սահմանի)" : ` / ${assistant.limit ?? 0} հարց`}
        </p>
        {assistant.unlimited ? null : (
          <div
            className="h-2 overflow-hidden rounded-full bg-surface-secondary"
            role="progressbar"
            aria-valuenow={percent}
            aria-valuemin={0}
            aria-valuemax={100}
          >
            <div
              className={cn("h-full rounded-full", percent >= 90 ? "bg-danger" : "bg-primary")}
              style={{ width: `${percent}%` }}
            />
          </div>
        )}
        <dl className="grid grid-cols-2 gap-3 text-sm">
          <Row label="Մնացել է" value={assistant.remaining === null ? "∞" : String(assistant.remaining)} />
          <Row label="Բոնուս հարցեր" value={String(assistant.bonus)} />
          <Row label="Նոր ամիս" value={formatDay(assistant.resetsAt)} />
        </dl>
      </div>
      {assistant.bonuses && assistant.bonuses.length > 0 ? (
        <ul className="divide-y divide-border border-t border-border text-sm">
          {assistant.bonuses.map((bonus) => (
            <li key={bonus.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <span className="text-ink-muted">{formatDay(bonus.createdAt)}</span>
              <span className="text-ink">
                {bonus.amount > 0 ? `+${bonus.amount}` : bonus.amount} հարց{bonus.givenBy ? ` · ${bonus.givenBy}` : ""}
              </span>
            </li>
          ))}
        </ul>
      ) : null}
    </Card>
  );
}

/** "Այսօր", "Երեկ", "5 օր առաջ". */
function sinceLabel(iso: string): string {
  const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000);
  if (days <= 0) return "Այսօր";
  if (days === 1) return "Երեկ";
  return `${days} օր առաջ`;
}

import type { SubscriptionDto, SubscriptionStatus } from "@/lib/adminApi";
import { Badge, type BadgeTone } from "../ui";
import { formatNumber } from "../tables/shared";

export const STATUS_TONES: Record<SubscriptionStatus, BadgeTone> = {
  trial: "info",
  active: "success",
  free: "primary",
  canceling: "warning",
  expired: "danger",
  unlimited: "default",
};

export const STATUS_LABELS: Record<SubscriptionStatus, string> = {
  trial: "Փորձաշրջան",
  active: "Վճարված",
  free: "Անվճար մուտք",
  canceling: "Անջատվում է",
  expired: "Ավարտված",
  unlimited: "Անժամկետ",
};

export function StatusBadge({ status }: { status: SubscriptionStatus }) {
  return <Badge tone={STATUS_TONES[status]}>{STATUS_LABELS[status]}</Badge>;
}

/** 3900 → "3 900 դր." */
export function amd(value: number): string {
  return `${formatNumber(value)} դր.`;
}

/** An ISO moment as the day in Yerevan: 05.11.2026. */
export function formatDay(iso: string | null): string {
  if (!iso) return "—";
  const parts = new Intl.DateTimeFormat("en-GB", {
    timeZone: "Asia/Yerevan",
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).formatToParts(new Date(iso));
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? "";
  return `${get("day")}.${get("month")}.${get("year")}`;
}

/** "5 օր" / "Այսօր" for what is left. */
export function daysLeftLabel(days: number | null): string {
  if (days === null) return "Անժամկետ";
  if (days <= 0) return "Ավարտված";
  return `${days} օր`;
}

/** One line on where the subscription stands: "Գործում է մինչև 05.11.2026 (24 օր)". */
export function subscriptionLine(subscription: Pick<SubscriptionDto, "status" | "endsAt" | "daysLeft">): string {
  const { status, endsAt, daysLeft } = subscription;
  if (status === "unlimited") return "Անժամկետ մուտք";
  if (status === "expired") return `Ավարտվել է ${formatDay(endsAt)}`;
  if (status === "canceling") return `Կանջատվի ${formatDay(endsAt)} (${daysLeftLabel(daysLeft)} հետո)`;
  // The end can come from payments and free access together: one "until".
  const lead = status === "trial" ? "Փորձաշրջան մինչև" : "Գործում է մինչև";
  return `${lead} ${formatDay(endsAt)} (${daysLeftLabel(daysLeft)})`;
}

/** The monthly price split: the plan, then each module on that is paid for. */
export function PriceBreakdown({ subscription }: { subscription: SubscriptionDto }) {
  const paid = subscription.pricing.modules.filter((m) => m.active && !m.included);
  const included = subscription.pricing.modules.filter((m) => m.active && m.included);

  return (
    <dl className="space-y-2 text-sm">
      <div className="flex justify-between gap-4">
        <dt className="text-ink-muted">Փաթեթ «{subscription.plan?.name ?? "—"}»</dt>
        <dd className="font-medium text-ink">{amd(subscription.pricing.plan)}</dd>
      </div>
      {included.length > 0 ? (
        <div className="flex justify-between gap-4">
          <dt className="text-ink-muted">Ներառված է՝ {included.map((m) => m.label).join(", ")}</dt>
          <dd className="text-ink-muted">0 դր.</dd>
        </div>
      ) : null}
      {paid.map((module) => (
        <div key={module.key} className="flex justify-between gap-4">
          <dt className="text-ink-muted">Մոդուլ «{module.label}»</dt>
          <dd className="font-medium text-ink">+ {amd(module.price)}</dd>
        </div>
      ))}
      <div className="flex justify-between gap-4 border-t border-border pt-2">
        <dt className="font-medium text-ink">Ընդամենը ամսական</dt>
        <dd className="text-base font-semibold text-ink">{amd(subscription.pricing.total)}</dd>
      </div>
    </dl>
  );
}

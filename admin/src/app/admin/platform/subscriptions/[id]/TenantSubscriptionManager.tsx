"use client";

import { useState, type FormEvent, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import type { TenantSubscriptionDetailDto } from "@/lib/adminApi";
import { Button, Card, EmptyRow, FieldLabel, Input, Select, TableWrap, Td, Th, Thead, Tr } from "../../../_components/ui";
import { PriceBreakdown, StatusBadge, amd, formatDay, subscriptionLine } from "../../../_components/subscription/shared";

type Unit = "day" | "week" | "month";

const UNIT_LABELS: Record<Unit, string> = { day: "օր", week: "շաբաթ", month: "ամիս" };

/** Everything the super admin does to one business's subscription. */
export function TenantSubscriptionManager({ data }: { data: TenantSubscriptionDetailDto }) {
  const router = useRouter();
  const { tenant, subscription, payments, plans } = data;
  const base = `/api/admin/tenants/${tenant.id}/subscription`;
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function call(path: string, method: string, body?: object): Promise<boolean> {
    setPending(true);
    setError(null);
    try {
      const response = await fetch(`${base}${path}`, {
        method,
        headers: { "Content-Type": "application/json" },
        body: body ? JSON.stringify(body) : undefined,
      });
      if (!response.ok) {
        const json = (await response.json().catch(() => null)) as { message?: string } | null;
        setError(json?.message ?? "Չհաջողվեց պահպանել։");
        return false;
      }
      router.refresh();
      return true;
    } finally {
      setPending(false);
    }
  }

  const canceling = subscription.status === "canceling";
  const expired = subscription.status === "expired";

  return (
    <div className="space-y-6">
      {error ? <p className="rounded-md bg-danger-light px-4 py-3 text-sm text-danger">{error}</p> : null}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-base font-semibold text-ink">Կարգավիճակ</h2>
            <StatusBadge status={subscription.status} />
          </div>
          <p className="text-sm text-ink">{subscriptionLine(subscription)}</p>
          <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <Dates label="Փորձաշրջան մինչև" value={subscription.trialEndsAt} />
            <Dates label="Վճարված մինչև" value={subscription.paidUntil} />
            <Dates label="Անվճար մինչև" value={subscription.freeUntil} />
            {subscription.cancelAt ? <Dates label="Անջատվում է" value={subscription.cancelAt} /> : null}
          </dl>

          <div className="space-y-1">
            <FieldLabel>Փաթեթ</FieldLabel>
            <Select
              value={subscription.plan?.id ?? ""}
              disabled={pending}
              onChange={(e) => call("", "PATCH", { plan_id: Number(e.target.value) })}
            >
              {subscription.plan ? null : <option value="">—</option>}
              {plans.map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.name} · {amd(plan.price)}
                  {plan.isActive ? "" : " (անջատված)"}
                </option>
              ))}
            </Select>
          </div>

          <label className="flex items-center gap-2 text-sm text-ink">
            <input
              type="checkbox"
              checked={subscription.unlimited && !subscription.cancelAt}
              disabled={pending}
              onChange={(e) => call("", "PATCH", { unlimited: e.target.checked })}
            />
            Անժամկետ անվճար մուտք (օր․ գործընկերների համար)
          </label>
        </Card>

        <Card className="space-y-4">
          <h2 className="text-base font-semibold text-ink">Ամսական վճար</h2>
          <PriceBreakdown subscription={subscription} />
          <p className="text-xs text-ink-muted">Մոդուլները միացվում են «Բիզնեսներ» էջում. գինը հաշվվում է ինքնաշխատ։</p>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <PaymentForm total={subscription.pricing.total} pending={pending} onSubmit={(body) => call("/payments", "POST", body)} />
        <FreeAccessForm
          pending={pending}
          freeUntil={subscription.freeUntil}
          onGrant={(body) => call("/free", "POST", body)}
          onRemove={() => call("/free", "DELETE")}
        />
        <Action title="Անջատել բաժանորդագրությունը">
          {canceling || (expired && subscription.cancelAt) ? (
            <>
              <p className="text-sm text-ink-muted">
                {expired ? "Անջատված է" : `Կանջատվի ${formatDay(subscription.endsAt)}-ին`}։ Բիզնեսի տվյալները պահպանվում են։
              </p>
              <Button variant="secondary" disabled={pending} onClick={() => call("/resume", "POST")}>
                Չեղարկել անջատումը
              </Button>
            </>
          ) : (
            <CancelForm warnDays={subscription.warnDays} pending={pending} onSubmit={(days) => call("/cancel", "POST", { days })} />
          )}
        </Action>
      </div>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-ink">Վճարումներ</h2>
        <TableWrap>
          <Thead>
            <Th>Ամսաթիվ</Th>
            <Th>Գումար</Th>
            <Th>Ամիս</Th>
            <Th>Ժամանակահատված</Th>
            <Th>Նշում</Th>
            <Th>Գրանցել է</Th>
            <Th />
          </Thead>
          <tbody>
            {payments.map((payment, index) => (
              <Tr key={payment.id}>
                <Td>{formatDay(payment.createdAt)}</Td>
                <Td className="font-medium">{amd(payment.amount)}</Td>
                <Td>{payment.months}</Td>
                <Td className="text-ink-muted">
                  {formatDay(payment.periodFrom)} – {formatDay(payment.periodTo)}
                </Td>
                <Td className="text-ink-muted">{payment.note ?? "—"}</Td>
                <Td className="text-ink-muted">{payment.recordedBy ?? "—"}</Td>
                <Td className="text-right">
                  {/* Only the latest one: an entry made by mistake. */}
                  {index === 0 ? (
                    <Button
                      variant="ghost"
                      size="sm"
                      disabled={pending}
                      onClick={() => {
                        if (confirm(`Հեռացնե՞լ ${amd(payment.amount)} վճարումը։ Վճարված ժամկետը կվերադառնա նախկինին։`)) {
                          void call(`/payments/${payment.id}`, "DELETE");
                        }
                      }}
                    >
                      Հեռացնել
                    </Button>
                  ) : null}
                </Td>
              </Tr>
            ))}
            {payments.length === 0 ? <EmptyRow colSpan={7}>Վճարումներ դեռ չկան։</EmptyRow> : null}
          </tbody>
        </TableWrap>
      </section>
    </div>
  );
}

function Dates({ label, value }: { label: string; value: string | null }) {
  return (
    <>
      <dt className="text-ink-muted">{label}</dt>
      <dd className="text-ink">{formatDay(value)}</dd>
    </>
  );
}

function Action({ title, children }: { title: string; children: ReactNode }) {
  return (
    <Card className="space-y-3">
      <h2 className="text-base font-semibold text-ink">{title}</h2>
      {children}
    </Card>
  );
}

function PaymentForm({
  total,
  pending,
  onSubmit,
}: {
  total: number;
  pending: boolean;
  onSubmit: (body: { months: number; amount: number | null; note: string | null }) => Promise<boolean>;
}) {
  const [months, setMonths] = useState("1");
  const [amount, setAmount] = useState("");
  const [note, setNote] = useState("");
  const count = Math.max(1, Number(months) || 1);

  async function submit(e: FormEvent) {
    e.preventDefault();
    const ok = await onSubmit({ months: count, amount: amount === "" ? null : Number(amount), note: note.trim() || null });
    if (ok) {
      setAmount("");
      setNote("");
    }
  }

  return (
    <Action title="Գրանցել վճարում">
      <form onSubmit={submit} className="space-y-3">
        <div className="grid grid-cols-2 gap-3">
          <div className="space-y-1">
            <FieldLabel>Ամիս</FieldLabel>
            <Input type="number" min={1} max={36} value={months} onChange={(e) => setMonths(e.target.value)} />
          </div>
          <div className="space-y-1">
            <FieldLabel>Գումար, դր.</FieldLabel>
            <Input type="number" min={0} placeholder={String(total * count)} value={amount} onChange={(e) => setAmount(e.target.value)} />
          </div>
        </div>
        <div className="space-y-1">
          <FieldLabel>Նշում</FieldLabel>
          <Input placeholder="Կանխիկ, Idram, փոխանցում…" value={note} onChange={(e) => setNote(e.target.value)} />
        </div>
        <p className="text-xs text-ink-muted">Ժամկետը երկարաձգվում է ընթացիկ ավարտից։ Դատարկ գումար՝ {amd(total * count)}։</p>
        <Button type="submit" disabled={pending}>
          Գրանցել
        </Button>
      </form>
    </Action>
  );
}

function FreeAccessForm({
  pending,
  freeUntil,
  onGrant,
  onRemove,
}: {
  pending: boolean;
  freeUntil: string | null;
  onGrant: (body: { amount: number; unit: Unit }) => Promise<boolean>;
  onRemove: () => void;
}) {
  const [amount, setAmount] = useState("1");
  const [unit, setUnit] = useState<Unit>("month");

  return (
    <Action title="Անվճար մուտք">
      <form
        onSubmit={(e) => {
          e.preventDefault();
          void onGrant({ amount: Math.max(1, Number(amount) || 1), unit });
        }}
        className="space-y-3"
      >
        <div className="grid grid-cols-2 gap-3">
          <Input type="number" min={1} max={365} value={amount} onChange={(e) => setAmount(e.target.value)} />
          <Select value={unit} onChange={(e) => setUnit(e.target.value as Unit)}>
            {(Object.keys(UNIT_LABELS) as Unit[]).map((u) => (
              <option key={u} value={u}>
                {UNIT_LABELS[u]}
              </option>
            ))}
          </Select>
        </div>
        <p className="text-xs text-ink-muted">Ավելանում է ընթացիկ ավարտից հետո. կարող եք նորից ավելացնել։</p>
        <div className="flex flex-wrap gap-2">
          <Button type="submit" disabled={pending}>
            Տալ անվճար մուտք
          </Button>
          {freeUntil ? (
            <Button
              type="button"
              variant="secondary"
              disabled={pending}
              onClick={() => {
                if (confirm("Հանե՞լ անվճար մուտքը։")) onRemove();
              }}
            >
              Հանել
            </Button>
          ) : null}
        </div>
      </form>
    </Action>
  );
}

function CancelForm({ warnDays, pending, onSubmit }: { warnDays: number; pending: boolean; onSubmit: (days: number) => void }) {
  const [days, setDays] = useState(String(warnDays));

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        const n = Math.max(0, Number(days) || 0);
        const when = n === 0 ? "հենց հիմա" : `${n} օր հետո`;
        if (confirm(`Անջատե՞լ բաժանորդագրությունը ${when}։ Բիզնեսը հաղորդագրություն կստանա, տվյալները կպահպանվեն։`)) onSubmit(n);
      }}
      className="space-y-3"
    >
      <div className="space-y-1">
        <FieldLabel>Քանի օր հետո</FieldLabel>
        <Input type="number" min={0} max={90} value={days} onChange={(e) => setDays(e.target.value)} />
      </div>
      <p className="text-xs text-ink-muted">Բիզնեսը հիմա կստանա նախազգուշացում չատում։ 0՝ անմիջապես։</p>
      <Button type="submit" variant="secondary" disabled={pending}>
        Անջատել
      </Button>
    </form>
  );
}

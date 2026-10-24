"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import type { ModulePriceDto, PlanDto } from "@/lib/adminApi";
import { Badge, Button, Card, FieldLabel, Input, SectionCard, Textarea } from "@/ui";
import { amd } from "../../_components/subscription/shared";

type Draft = {
  name: string;
  description: string;
  price: string;
  trial_days: string;
  /** Empty: no limit. */
  assistant_requests: string;
  included_modules: string[];
  is_default: boolean;
  is_active: boolean;
};

function draftOf(plan?: PlanDto): Draft {
  return {
    name: plan?.name ?? "",
    description: plan?.description ?? "",
    price: String(plan?.price ?? ""),
    trial_days: String(plan?.trialDays ?? 14),
    assistant_requests: plan ? (plan.assistantRequests === null ? "" : String(plan.assistantRequests)) : "30",
    included_modules: plan?.includedModules ?? [],
    is_default: plan?.isDefault ?? false,
    is_active: plan?.isActive ?? true,
  };
}

function payload(draft: Draft) {
  return {
    ...draft,
    description: draft.description.trim() || null,
    price: Math.max(0, Number(draft.price) || 0),
    trial_days: Math.max(0, Number(draft.trial_days) || 0),
    assistant_requests: draft.assistant_requests.trim() === "" ? null : Math.max(0, Math.round(Number(draft.assistant_requests) || 0)),
  };
}

/** The platform's plans: each one's price, trial, AI assistant requests and included modules. */
export function PlansManager({ plans, modules }: { plans: PlanDto[]; modules: ModulePriceDto[] }) {
  const [adding, setAdding] = useState(false);

  return (
    <SectionCard
      title="Փաթեթներ"
      actions={
        adding ? null : (
          <Button size="sm" onClick={() => setAdding(true)}>
            Նոր փաթեթ
          </Button>
        )
      }
    >
      <div className="grid gap-4 lg:grid-cols-2">
        {plans.map((plan) => (
          <PlanCard key={plan.id} plan={plan} modules={modules} />
        ))}
        {adding ? <PlanForm modules={modules} onDone={() => setAdding(false)} /> : null}
      </div>
    </SectionCard>
  );
}

function PlanCard({ plan, modules }: { plan: PlanDto; modules: ModulePriceDto[] }) {
  const router = useRouter();
  const [editing, setEditing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (editing) return <PlanForm plan={plan} modules={modules} onDone={() => setEditing(false)} />;

  async function remove() {
    if (!confirm(`Ջնջե՞լ «${plan.name}» փաթեթը։`)) return;
    setError(null);
    const response = await fetch(`/api/admin/plans/${plan.id}`, { method: "DELETE" });
    if (!response.ok) {
      const json = (await response.json().catch(() => null)) as { message?: string } | null;
      setError(json?.message ?? "Չհաջողվեց ջնջել։");
      return;
    }
    router.refresh();
  }

  const included = modules.filter((m) => plan.includedModules.includes(m.key)).map((m) => m.label);

  return (
    <Card className="space-y-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="flex flex-wrap items-center gap-2 font-semibold text-ink">
            {plan.name}
            {plan.isDefault ? <Badge tone="primary">Հիմնական</Badge> : null}
            {plan.isActive ? null : <Badge>Անջատված</Badge>}
          </p>
          {plan.description ? <p className="mt-1 text-sm text-ink-muted">{plan.description}</p> : null}
        </div>
        <p className="text-lg font-semibold text-ink">{amd(plan.price)}/ամիս</p>
      </div>
      <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
        <dt className="text-ink-muted">Փորձաշրջան</dt>
        <dd className="text-ink">{plan.trialDays} օր</dd>
        <dt className="text-ink-muted">AI հարցեր ամսական</dt>
        <dd className="text-ink">{plan.assistantRequests === null ? "Անսահմանափակ" : plan.assistantRequests}</dd>
        <dt className="text-ink-muted">Ներառված մոդուլներ</dt>
        <dd className="text-ink">{included.length ? included.join(", ") : "—"}</dd>
        <dt className="text-ink-muted">Բիզնեսներ</dt>
        <dd className="text-ink">{plan.subscriptionsCount ?? 0}</dd>
      </dl>
      {error ? <p className="text-sm text-danger">{error}</p> : null}
      <div className="flex gap-2">
        <Button size="sm" variant="secondary" onClick={() => setEditing(true)}>
          Խմբագրել
        </Button>
        <Button size="sm" variant="ghost" onClick={remove}>
          Ջնջել
        </Button>
      </div>
    </Card>
  );
}

function PlanForm({ plan, modules, onDone }: { plan?: PlanDto; modules: ModulePriceDto[]; onDone: () => void }) {
  const router = useRouter();
  const [draft, setDraft] = useState<Draft>(draftOf(plan));
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);
    try {
      const response = await fetch(plan ? `/api/admin/plans/${plan.id}` : "/api/admin/plans", {
        method: plan ? "PATCH" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload(draft)),
      });
      if (!response.ok) {
        setError("Ստուգեք անունը և գինը։");
        return;
      }
      router.refresh();
      onDone();
    } finally {
      setPending(false);
    }
  }

  function toggleModule(key: string) {
    setDraft({
      ...draft,
      included_modules: draft.included_modules.includes(key)
        ? draft.included_modules.filter((k) => k !== key)
        : [...draft.included_modules, key],
    });
  }

  return (
    <Card>
      <form onSubmit={submit} className="space-y-3">
        <div className="space-y-1">
          <FieldLabel>Անուն</FieldLabel>
          <Input required value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
        </div>
        <div className="space-y-1">
          <FieldLabel>Նկարագրություն</FieldLabel>
          <Textarea rows={2} value={draft.description} onChange={(e) => setDraft({ ...draft, description: e.target.value })} />
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div className="space-y-1">
            <FieldLabel>Գին ամսական, դր.</FieldLabel>
            <Input required type="number" min={0} value={draft.price} onChange={(e) => setDraft({ ...draft, price: e.target.value })} />
          </div>
          <div className="space-y-1">
            <FieldLabel>Փորձաշրջան, օր</FieldLabel>
            <Input type="number" min={0} max={365} value={draft.trial_days} onChange={(e) => setDraft({ ...draft, trial_days: e.target.value })} />
          </div>
        </div>
        <div className="space-y-1">
          <FieldLabel>AI օգնականի հարցեր ամսական</FieldLabel>
          <Input
            type="number"
            min={0}
            max={100000}
            placeholder="Անսահմանափակ"
            value={draft.assistant_requests}
            onChange={(e) => setDraft({ ...draft, assistant_requests: e.target.value })}
          />
          <p className="text-xs text-ink-muted">
            Ամիսը հաշվվում է բիզնեսի վճարման օրից։ Դատարկ՝ առանց սահմանափակման, 0՝ առանց AI օգնականի։
          </p>
        </div>
        <div className="space-y-1">
          <FieldLabel>Գնի մեջ ներառված մոդուլներ</FieldLabel>
          <div className="flex flex-wrap gap-x-4 gap-y-1">
            {modules.map((module) => (
              <label key={module.key} className="flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" checked={draft.included_modules.includes(module.key)} onChange={() => toggleModule(module.key)} />
                {module.label}
              </label>
            ))}
          </div>
        </div>
        <div className="flex flex-wrap gap-x-4 gap-y-1">
          <label className="flex items-center gap-2 text-sm text-ink">
            <input type="checkbox" checked={draft.is_default} onChange={(e) => setDraft({ ...draft, is_default: e.target.checked })} />
            Հիմնական (նոր բիզնեսների համար)
          </label>
          <label className="flex items-center gap-2 text-sm text-ink">
            <input type="checkbox" checked={draft.is_active} onChange={(e) => setDraft({ ...draft, is_active: e.target.checked })} />
            Ակտիվ
          </label>
        </div>
        {error ? <p className="text-sm text-danger">{error}</p> : null}
        <div className="flex gap-2">
          <Button type="submit" disabled={pending}>
            Պահպանել
          </Button>
          <Button type="button" variant="ghost" onClick={onDone}>
            Չեղարկել
          </Button>
        </div>
      </form>
    </Card>
  );
}

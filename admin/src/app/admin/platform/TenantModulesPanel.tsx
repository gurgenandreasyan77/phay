"use client";

import { useEffect, useState } from "react";
import type { AdminFeatureFlagDto, AdminTenantDto, AgentDto } from "@/lib/adminApi";
import { Badge, Button, Card, EmptyCard, SectionCard, cn } from "@/ui";
import { amd } from "../_components/subscription/shared";
import { AgentAvatar } from "../_components/agents/AgentAvatar";

async function fetchModules(tenantId: number): Promise<AdminFeatureFlagDto[] | null> {
  const response = await fetch(`/api/admin/tenants/${tenantId}/features`, {
    cache: "no-store",
  });
  if (!response.ok) return null;
  const json = (await response.json()) as { data: AdminFeatureFlagDto[] };
  return json.data;
}

/**
 * Managed modules of one business (Services, Market, Site). A module that is
 * off doesn't exist for that business: not in its admin menu, not on its
 * site, and its API answers 404.
 */
export function TenantModulesPanel({ tenant, onClose }: { tenant: AdminTenantDto; onClose: () => void }) {
  const [modules, setModules] = useState<AdminFeatureFlagDto[] | null>(null);
  const [loadError, setLoadError] = useState(false);

  // Remounted per business (key={tenant.id}), so there's no state to reset here.
  useEffect(() => {
    let cancelled = false;
    fetchModules(tenant.id).then((data) => {
      if (cancelled) return;
      setLoadError(data === null);
      if (data) setModules(data);
    });
    return () => {
      cancelled = true;
    };
  }, [tenant.id]);

  return (
    <SectionCard
      title={`«${tenant.name}» բիզնեսի մոդուլները`}
      description="Կառավարվող մոդուլներ՝ ծառայություններ, խանութ և կայքի կառավարում։ Պատվերներն ու վաճառքները կան յուրաքանչյուր բիզնեսում և չեն միացվում։ Անջատված մոդուլը չի երևում այդ բիզնեսի ադմինում և կայքում։"
      actions={
        <Button size="sm" variant="ghost" onClick={onClose}>
          Փակել
        </Button>
      }
    >
      <div className="space-y-3">
        {loadError ? <EmptyCard>Չհաջողվեց բեռնել մոդուլները։</EmptyCard> : null}
        {!modules && !loadError ? <EmptyCard>Բեռնվում է…</EmptyCard> : null}
        {(modules ?? []).map((module) => (
          <ModuleRow key={module.key} tenantId={tenant.id} module={module} />
        ))}
      </div>
      <TenantAgents tenantId={tenant.id} />
    </SectionCard>
  );
}

async function fetchAgents(tenantId: number): Promise<AgentDto[] | null> {
  const response = await fetch(`/api/admin/tenants/${tenantId}/agents`, {
    cache: "no-store",
  });
  if (!response.ok) return null;
  const json = (await response.json()) as { data: AgentDto[] };
  return json.data;
}

/**
 * The business's AI agents, turned on like modules: on, the agent joins its
 * team (the business is told in its chat) and its price adds to the
 * monthly payment unless the plan includes it.
 */
function TenantAgents({ tenantId }: { tenantId: number }) {
  const [agents, setAgents] = useState<AgentDto[] | null>(null);
  const [loadError, setLoadError] = useState(false);

  useEffect(() => {
    let cancelled = false;
    fetchAgents(tenantId).then((data) => {
      if (cancelled) return;
      setLoadError(data === null);
      if (data) setAgents(data);
    });
    return () => {
      cancelled = true;
    };
  }, [tenantId]);

  return (
    <div className="mt-6 space-y-3">
      <div>
        <h3 className="text-sm font-semibold text-ink">AI աշխատակիցներ</h3>
        <p className="text-sm text-ink-muted">
          Միացնելիս բիզնեսը կստանա հաղորդագրություն չատում, իսկ աշխատակցի գինը կավելանա ամսական վճարին (եթե ներառված չէ փաթեթում)։
        </p>
      </div>
      {loadError ? <EmptyCard>Չհաջողվեց բեռնել AI աշխատակիցներին։</EmptyCard> : null}
      {!agents && !loadError ? <EmptyCard>Բեռնվում է…</EmptyCard> : null}
      <div className="grid gap-3 md:grid-cols-2">
        {(agents ?? []).map((agent) => (
          <AgentRow key={agent.key} tenantId={tenantId} agent={agent} />
        ))}
      </div>
    </div>
  );
}

function AgentRow({ tenantId, agent }: { tenantId: number; agent: AgentDto }) {
  const [active, setActive] = useState(agent.active);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);

  async function toggle() {
    const next = !active;
    setPending(true);
    setError(false);
    try {
      const response = await fetch(`/api/admin/tenants/${tenantId}/agents/${agent.key}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ active: next }),
      });
      if (!response.ok) {
        setError(true);
        return;
      }
      setActive(next);
    } catch {
      setError(true);
    } finally {
      setPending(false);
    }
  }

  return (
    <Card className="flex items-center gap-3">
      <AgentAvatar agentKey={agent.key} size={48} active={active} />
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold text-ink">{agent.label}</p>
        <p className="text-xs text-ink-muted">{agent.included ? "Ներառված է փաթեթում" : `${amd(agent.price)} / ամիս`}</p>
        {error ? <p className="text-xs text-danger">Չհաջողվեց պահպանել։</p> : null}
      </div>
      <Switch label={agent.label} checked={active} disabled={pending} onChange={toggle} />
    </Card>
  );
}

function Switch({
  label,
  checked,
  disabled,
  onChange,
}: {
  label: string;
  checked: boolean;
  disabled: boolean;
  onChange: () => void;
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      disabled={disabled}
      onClick={onChange}
      className={cn(
        "relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50",
        checked ? "bg-primary" : "bg-surface-secondary",
      )}
    >
      <span
        className={cn(
          "inline-block h-5 w-5 translate-x-0.5 rounded-full bg-white shadow transition-transform",
          checked && "translate-x-[22px]",
        )}
      />
    </button>
  );
}

function ModuleRow({ tenantId, module }: { tenantId: number; module: AdminFeatureFlagDto }) {
  const [active, setActive] = useState(module.active);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);

  async function toggle() {
    const next = !active;
    setPending(true);
    setError(false);
    try {
      const response = await fetch(`/api/admin/tenants/${tenantId}/features/${module.key}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ active: next }),
      });
      if (!response.ok) {
        setError(true);
        return;
      }
      setActive(next);
    } catch {
      setError(true);
    } finally {
      setPending(false);
    }
  }

  return (
    <Card className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div className="space-y-1.5">
        <div className="flex items-center gap-2">
          <h3 className="text-sm font-semibold text-ink">{module.label}</h3>
          <Badge tone={active ? "success" : "default"}>{active ? "Միացված" : "Անջատված"}</Badge>
        </div>
        <p className="max-w-xl text-sm text-ink-muted">{module.description}</p>
        {error ? <p className="text-sm text-danger">Չհաջողվեց պահպանել։</p> : null}
      </div>

      <Switch label={module.label} checked={active} disabled={pending} onChange={toggle} />
    </Card>
  );
}

"use client";

import { useEffect, useState } from "react";
import type { AdminFeatureFlagDto, AdminTenantDto } from "@/lib/adminApi";
import { Badge, Button, Card, EmptyCard, SectionCard, cn } from "../_components/ui";

async function fetchModules(tenantId: number): Promise<AdminFeatureFlagDto[] | null> {
  const response = await fetch(`/api/admin/tenants/${tenantId}/features`, { cache: "no-store" });
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
    </SectionCard>
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

      <button
        type="button"
        role="switch"
        aria-checked={active}
        aria-label={module.label}
        disabled={pending}
        onClick={toggle}
        className={cn(
          "relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50",
          active ? "bg-primary" : "bg-surface-secondary",
        )}
      >
        <span
          className={cn(
            "inline-block h-5 w-5 translate-x-0.5 rounded-full bg-white shadow transition-transform",
            active && "translate-x-[22px]",
          )}
        />
      </button>
    </Card>
  );
}

"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminFeatureFlagDto } from "@/lib/adminApi";
import { Badge, Card, cn } from "../_components/ui";

export function FeatureFlagsManager({
  flags,
  canManage,
}: {
  flags: AdminFeatureFlagDto[];
  canManage: boolean;
}) {
  return (
    <div className="space-y-3">
      {!canManage ? (
        <p className="text-sm text-ink-muted">
          Միայն Super Admin-ը կարող է փոխել այս անջատիչները։ Դուք կարող եք տեսնել ընթացիկ կարգավիճակը։
        </p>
      ) : null}

      {flags.map((flag) => (
        <FeatureFlagRow key={flag.key} flag={flag} canManage={canManage} />
      ))}
    </div>
  );
}

function FeatureFlagRow({ flag, canManage }: { flag: AdminFeatureFlagDto; canManage: boolean }) {
  const router = useRouter();
  const [active, setActive] = useState(flag.active);
  const [pending, setPending] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState(false);

  async function toggle() {
    const next = !active;
    setPending(true);
    setError(false);
    setNotice(null);

    try {
      const response = await fetch(`/api/admin/feature-flags/${flag.key}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ active: next }),
      });

      if (!response.ok) {
        setError(true);
        return;
      }

      setActive(next);
      setNotice(next ? `«${flag.label}» բաժինը միացված է։` : `«${flag.label}» բաժինը անջատված է։`);
      router.refresh();
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
          <h3 className="text-sm font-semibold text-ink">{flag.label}</h3>
          <code className="rounded bg-surface-secondary px-1.5 py-0.5 text-xs text-ink-muted">{flag.key}</code>
          <Badge tone={active ? "success" : "default"}>{active ? "Ակտիվ" : "Անջատված"}</Badge>
        </div>
        <p className="max-w-xl text-sm text-ink-muted">{flag.description}</p>
        <p className="text-xs text-ink-faint">
          {flag.lastChangedAt
            ? `Վերջին փոփոխությունը՝ ${new Date(flag.lastChangedAt).toLocaleString("hy-AM")}${
                flag.lastChangedBy ? ` (${flag.lastChangedBy})` : ""
              }`
            : "Դեռ չի փոփոխվել։"}
        </p>
        {notice ? <p className="text-sm text-success">{notice}</p> : null}
        {error ? <p className="text-sm text-danger">Չհաջողվեց պահպանել։</p> : null}
      </div>

      <button
        type="button"
        role="switch"
        aria-checked={active}
        aria-label={flag.label}
        disabled={!canManage || pending}
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

"use client";

import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { Building2, Check, ChevronDown } from "lucide-react";
import type { AdminTenantDto } from "@/lib/adminApi";
import { cn } from "./ui";

/**
 * Shows which business the admin is working on and, when the user has more
 * than one (a super admin has all of them), switches between them.
 */
export function TenantSwitcher({ current, tenants }: { current: AdminTenantDto; tenants: AdminTenantDto[] }) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    function handleClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, [open]);

  async function switchTo(tenant: AdminTenantDto) {
    if (tenant.id === current.id) {
      setOpen(false);
      return;
    }
    setPending(true);
    setError(false);
    try {
      const response = await fetch("/api/admin/switch-tenant", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ tenant_id: tenant.id }),
      });
      if (!response.ok) {
        setError(true);
        return;
      }
      setOpen(false);
      // An open edit page (e.g. a product) belongs to the previous business.
      router.push("/admin");
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  const label = (
    <>
      <Building2 className="h-4 w-4 shrink-0 text-ink-muted" strokeWidth={1.75} />
      <span className="max-w-40 truncate font-medium text-ink">{current.name}</span>
    </>
  );

  if (tenants.length <= 1) {
    return <div className="hidden items-center gap-2 px-2 text-sm sm:flex">{label}</div>;
  }

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        disabled={pending}
        aria-haspopup="listbox"
        aria-expanded={open}
        title="Փոխել բիզնեսը"
        className="flex items-center gap-2 rounded-md border border-border px-2.5 py-1.5 text-sm hover:bg-surface-secondary disabled:opacity-50"
      >
        {label}
        <ChevronDown className="h-3.5 w-3.5 text-ink-muted" strokeWidth={1.75} />
      </button>

      {open ? (
        <div className="absolute right-0 top-full z-30 mt-1.5 w-64 rounded-lg border border-border bg-surface p-1.5 shadow-md">
          <p className="px-2.5 pb-1.5 pt-1 text-xs font-medium text-ink-muted">Բիզնես</p>
          <ul role="listbox" aria-label="Բիզնես" className="max-h-72 overflow-y-auto">
            {tenants.map((tenant) => (
              <li key={tenant.id}>
                <button
                  type="button"
                  role="option"
                  aria-selected={tenant.id === current.id}
                  onClick={() => switchTo(tenant)}
                  disabled={pending}
                  className={cn(
                    "flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm hover:bg-surface-secondary",
                    tenant.id === current.id ? "font-medium text-ink" : "text-ink-muted",
                  )}
                >
                  <span className="flex-1 truncate">{tenant.name}</span>
                  {tenant.status === "suspended" ? <span className="text-xs text-warning">Կասեցված</span> : null}
                  {tenant.id === current.id ? <Check className="h-4 w-4 text-primary" strokeWidth={2} /> : null}
                </button>
              </li>
            ))}
          </ul>
          {error ? <p className="px-2.5 py-1.5 text-xs text-danger">Չհաջողվեց փոխել բիզնեսը։</p> : null}
        </div>
      ) : null}
    </div>
  );
}

"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Wallet } from "lucide-react";
import { ConfirmDialog, type ConfirmContent } from "@/ui/dialog/ConfirmDialog";
import { Button, cn } from "@/ui";

export type BalancePurchase =
  | { action: "subscription"; months: number }
  | { action: "plan"; plan_id: number }
  | { action: "module"; module: string };

/**
 * Pays from the balance at once, after «are you sure?» with the figures:
 * months of the subscription, a bigger plan or a module. The API's reason
 * (not enough on the balance) shows in the dialog. `className` replaces the
 * button's own look (cards in colour).
 */
export function PayFromBalance({
  purchase,
  label,
  confirm,
  primary,
  className,
}: {
  purchase: BalancePurchase;
  label: string;
  confirm: ConfirmContent;
  primary?: boolean;
  className?: string;
}) {
  const router = useRouter();
  const [state, setState] = useState<"idle" | "asking" | "paying">("idle");
  const [error, setError] = useState<string | null>(null);

  async function pay() {
    setState("paying");
    setError(null);
    const { action, ...body } = purchase;
    const response = await fetch(`/api/admin/balance/${action}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    }).catch(() => null);
    const json = (await response?.json().catch(() => null)) as { message?: string; errors?: Record<string, string[]> } | null;
    if (!response?.ok) {
      setState("asking");
      setError(Object.values(json?.errors ?? {})[0]?.[0] ?? json?.message ?? "Չհաջողվեց վճարել։ Փորձեք կրկին։");
      return;
    }
    setState("idle");
    router.refresh();
  }

  return (
    <>
      {className ? (
        <button
          type="button"
          className={cn("inline-flex items-center justify-center gap-2 rounded-md px-4 text-sm font-medium", className)}
          onClick={() => setState("asking")}
        >
          <Wallet className="h-4 w-4" />
          {label}
        </button>
      ) : (
        <Button variant={primary ? "primary" : "secondary"} size={primary ? "md" : "sm"} onClick={() => setState("asking")}>
          <Wallet className="h-4 w-4" />
          {label}
        </Button>
      )}
      <ConfirmDialog
        open={state !== "idle"}
        content={confirm}
        pending={state === "paying"}
        error={error}
        onConfirm={pay}
        onCancel={() => {
          setState("idle");
          setError(null);
        }}
      />
    </>
  );
}

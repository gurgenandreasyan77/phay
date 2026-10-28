"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ChevronDown, Plus, Wallet } from "lucide-react";
import type { BalanceDto } from "@/lib/adminApi";
import { Button, cn, FieldLabel, Input } from "@/ui";
import { amd, formatDay } from "../subscription/shared";
import { BalanceHistory } from "./BalanceHistory";

/**
 * A business's balance for the super admin: what it holds, topping it up
 * when a receipt comes in the chat (which pays its open request for AI
 * agents and renews what ended), a correction for a wrong top-up, and the
 * ledger. Loads itself when no `initial` comes (the chat page).
 */
export function PlatformBalancePanel({
  tenantId,
  initial,
  compact = false,
}: {
  tenantId: number;
  initial?: BalanceDto | null;
  /** Next to the chat: the ledger folded. */
  compact?: boolean;
}) {
  const router = useRouter();
  const [balance, setBalance] = useState<BalanceDto | null>(initial ?? null);
  const [amount, setAmount] = useState("");
  const [note, setNote] = useState("");
  const [correcting, setCorrecting] = useState(false);
  const [showHistory, setShowHistory] = useState(!compact);
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState<{ tone: "ok" | "error"; text: string } | null>(null);

  useEffect(() => {
    if (initial) return;
    let live = true;
    fetch(`/api/admin/tenants/${tenantId}/balance`, { cache: "no-store" })
      .then((response) => response.json())
      .then((json: { data?: BalanceDto }) => {
        if (live && json?.data) setBalance(json.data);
      })
      .catch(() => {});
    return () => {
      live = false;
    };
  }, [initial, tenantId]);

  async function submit() {
    const value = Number(amount);
    if (!amount || !Number.isFinite(value) || value === 0) return;
    setPending(true);
    setMessage(null);
    const response = await fetch(`/api/admin/tenants/${tenantId}/balance/${correcting ? "corrections" : "top-ups"}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ amount: value, note: note.trim() || null }),
    }).catch(() => null);
    const json = (await response?.json().catch(() => null)) as {
      data?: BalanceDto;
      message?: string;
      errors?: Record<string, string[]>;
    } | null;
    setPending(false);
    if (!response?.ok || !json?.data) {
      setMessage({ tone: "error", text: Object.values(json?.errors ?? {})[0]?.[0] ?? json?.message ?? "Չհաջողվեց պահպանել։" });
      return;
    }
    setBalance(json.data);
    setAmount("");
    setNote("");
    setMessage({
      tone: "ok",
      text: correcting
        ? `Ճշգրտված է. հաշվեկշիռը՝ ${amd(json.data.amount)}`
        : `Համալրված է, հաշվեկշիռը՝ ${amd(json.data.amount)} Բիզնեսին գրվել է չատում։`,
    });
    router.refresh();
  }

  return (
    <div className="space-y-3 rounded-xl border border-border bg-surface p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-light text-primary">
            <Wallet className="h-5 w-5" />
          </span>
          <div>
            <p className="text-xs text-ink-muted">Հաշվեկշիռ</p>
            <p className="text-xl font-semibold tracking-tight text-ink">{balance ? amd(balance.amount) : "…"}</p>
          </div>
        </div>
        {balance ? (
          <p className="text-xs text-ink-muted">
            {balance.autoRenew ? "Ինքնաշխատ երկարացումը միացված է" : "Ինքնաշխատ երկարացումն անջատված է"}
            {balance.upcoming[0]
              ? ` · հաջորդը՝ ${balance.upcoming[0].label}, ${formatDay(balance.upcoming[0].date)}, ${amd(balance.upcoming[0].amount)}`
              : ""}
          </p>
        ) : null}
      </div>

      <div className="grid gap-2 sm:grid-cols-[9rem_1fr_auto] sm:items-end">
        <div className="space-y-1">
          <FieldLabel>{correcting ? "Ճշգրտում (±), դր." : "Գումար, դր."}</FieldLabel>
          <Input type="number" min={correcting ? undefined : 1} value={amount} onChange={(e) => setAmount(e.target.value)} />
        </div>
        <div className="space-y-1">
          <FieldLabel>{correcting ? "Պատճառը" : "Նշում (անդորրագիր)"}</FieldLabel>
          <Input
            placeholder={correcting ? "Սխալ գումար էր մուտքագրված" : "Idram, փոխանցում, անդորրագիր #…"}
            value={note}
            onChange={(e) => setNote(e.target.value)}
          />
        </div>
        <Button onClick={submit} disabled={pending || !amount || (correcting && !note.trim())}>
          <Plus className="h-4 w-4" />
          {pending ? "Պահպանվում է…" : correcting ? "Ճշգրտել" : "Համալրել"}
        </Button>
      </div>

      {message ? (
        <p
          className={cn(
            "rounded-md px-3 py-2 text-sm",
            message.tone === "ok" ? "bg-success-light text-success" : "bg-danger-light text-danger",
          )}
        >
          {message.text}
        </p>
      ) : null}

      <div className="flex flex-wrap items-center gap-4 text-xs">
        <button type="button" className="text-ink-muted hover:text-ink hover:underline" onClick={() => setCorrecting((c) => !c)}>
          {correcting ? "← Համալրում" : "Ճշգրտել սխալ համալրումը"}
        </button>
        {balance && balance.transactions.length > 0 ? (
          <button
            type="button"
            className="inline-flex items-center gap-1 text-primary hover:underline"
            onClick={() => setShowHistory((s) => !s)}
          >
            Պատմություն
            <ChevronDown className={cn("h-3.5 w-3.5 transition", showHistory && "rotate-180")} />
          </button>
        ) : null}
      </div>

      {balance && showHistory ? <BalanceHistory transactions={balance.transactions} platform /> : null}
    </div>
  );
}

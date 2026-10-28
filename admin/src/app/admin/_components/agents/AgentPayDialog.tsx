"use client";

import Link from "next/link";
import { useEffect, useMemo, useState, type CSSProperties } from "react";
import { useRouter } from "next/navigation";
import { CalendarCheck, Check, Minus, Plus } from "lucide-react";
import type { AgentBillingAgentDto, AgentQuoteDto } from "@/lib/adminApi";
import { Button, cn, FieldLabel, Input } from "@/ui";
import { Dialog, DialogClose } from "@/ui/dialog/Dialog";
import { amd, formatDay } from "../subscription/shared";
import { AgentAvatar, agentTintStyle } from "./AgentAvatar";
import { useCountUp } from "./AgentTermRing";
import { TermTimeline } from "./TermTimeline";
import { isoDay, todayDay } from "./terms";
import styles from "./terms.module.css";

export type PayMode = "months" | "day";

/**
 * Paying for AI agents, each on its own term: some months from each one's
 * own end, or every chosen agent to one common day (the difference by the
 * day), so they are all paid on the same day from then on. The quote comes
 * from the API as the choice changes; the timeline shows the faces sliding
 * to the billing day. The business sends it as a request (Phay confirms it
 * in the chat) or, with a balance that covers it, pays from it at once;
 * the super admin records it as paid (`platform`). Mounted
 * per opening, so it starts from what the button that opened it asks.
 */
export function AgentPayDialog({
  open,
  onClose,
  agents,
  initialMode,
  initialSelected,
  subscriptionEndsAt,
  quoteUrl,
  submitUrl,
  platform = false,
  balance = null,
}: {
  open: boolean;
  onClose: () => void;
  /** Agents money can move (not inside the plan). */
  agents: AgentBillingAgentDto[];
  initialMode: PayMode;
  initialSelected: string[];
  subscriptionEndsAt: string | null;
  quoteUrl: string;
  submitUrl: string;
  platform?: boolean;
  /** The business's balance (dram); null without one to pay from. */
  balance?: number | null;
}) {
  const router = useRouter();
  const [mode, setMode] = useState<PayMode>(initialMode);
  const [selected, setSelected] = useState<string[]>(initialSelected);
  const [months, setMonths] = useState(initialMode === "months" ? 1 : 0);
  const [day, setDay] = useState("");
  const [amount, setAmount] = useState("");
  const [note, setNote] = useState("");
  const [quote, setQuote] = useState<AgentQuoteDto | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [state, setState] = useState<"idle" | "sending" | "sent" | "paid">("idle");

  // The earliest common day: no agent's end can move back.
  const latestDay = useMemo(() => {
    const today = todayDay();
    const ends = agents
      .filter((agent) => selected.includes(agent.key))
      .map((agent) => isoDay(agent.endsAt))
      .filter((d) => d > today);
    return ends.length > 0 ? ends.sort().at(-1)! : today;
  }, [agents, selected]);
  const subscriptionDay = isoDay(subscriptionEndsAt);
  const effectiveDay = mode === "day" ? (day && day >= latestDay ? day : latestDay) : "";

  const ready = open && selected.length > 0 && (mode === "day" || months >= 1);

  // Ask the API what it costs as the choice settles.
  useEffect(() => {
    if (!ready) return;
    const controller = new AbortController();
    const timer = setTimeout(async () => {
      try {
        const response = await fetch(quoteUrl, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ agents: selected, months, ...(mode === "day" ? { until: effectiveDay } : {}) }),
          signal: controller.signal,
        });
        const json = (await response.json().catch(() => null)) as { data?: AgentQuoteDto; message?: string } | null;
        if (!response.ok || !json?.data) {
          setQuote(null);
          setError(json?.message ?? "Չհաջողվեց հաշվել գումարը։");
          return;
        }
        setError(null);
        setQuote(json.data);
      } catch {
        // Aborted by a newer choice.
      }
    }, 220);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [ready, selected, months, mode, effectiveDay, quoteUrl]);

  const shown = ready ? quote : null;
  // The balance pays at once when it covers the total.
  const covers = !platform && balance !== null && shown !== null && shown.total <= balance;
  const total = useCountUp(shown?.total ?? 0, 600);

  async function submit(fromBalance = false) {
    setState("sending");
    setError(null);
    const response = await fetch(fromBalance ? "/api/admin/balance/agents" : submitUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        agents: selected,
        months,
        ...(mode === "day" ? { until: effectiveDay } : {}),
        ...(platform ? { amount: amount === "" ? null : Number(amount), note: note.trim() || null } : {}),
      }),
    }).catch(() => null);
    const json = (await response?.json().catch(() => null)) as { message?: string; errors?: Record<string, string[]> } | null;
    if (!response?.ok) {
      setState("idle");
      setError(Object.values(json?.errors ?? {})[0]?.[0] ?? json?.message ?? "Չհաջողվեց ուղարկել։ Փորձեք կրկին։");
      return;
    }
    router.refresh();
    if (platform) {
      onClose();
      return;
    }
    setState(fromBalance ? "paid" : "sent");
  }

  function toggle(key: string) {
    setSelected((keys) => (keys.includes(key) ? keys.filter((k) => k !== key) : [...keys, key]));
  }

  const timeline = agents
    .filter((agent) => agent.status !== "off" || selected.includes(agent.key))
    .map((agent) => ({
      key: agent.key,
      name: agent.persona.hy,
      // A new agent starts today.
      endsAt: agent.endsAt,
      moving: mode === "day" && selected.includes(agent.key),
    }));
  const targetIso = mode === "day" && effectiveDay ? `${effectiveDay}T23:59:59+04:00` : null;

  return (
    <Dialog open={open} onClose={onClose} labelledBy="agent-pay-title" className="w-[min(44rem,calc(100vw-2rem))]">
      {state === "paid" ? (
        <div className="space-y-4 text-center">
          <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success-light text-success">
            <Check className="h-7 w-7" />
          </span>
          <h2 id="agent-pay-title" className="text-lg font-semibold text-ink">
            Վճարված է հաշվեկշռից
          </h2>
          <p className="text-sm text-ink-muted">AI աշխատակիցների ժամկետներն արդեն երկարաձգված են։</p>
          <Button onClick={onClose}>Լավ</Button>
        </div>
      ) : state === "sent" ? (
        <div className="space-y-4 text-center">
          <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success-light text-success">
            <Check className="h-7 w-7" />
          </span>
          <h2 id="agent-pay-title" className="text-lg font-semibold text-ink">
            Հայտը ուղարկված է
          </h2>
          <p className="text-sm text-ink-muted">
            Phay-ի թիմը{" "}
            <Link href="/admin/support" className="font-medium text-primary underline underline-offset-2">
              չատում
            </Link>{" "}
            կգրի վճարման մանրամասները։ Վճարելուց հետո AI աշխատակիցների ժամկետները կթարմացվեն ինքնաշխատ։
          </p>
          <Button onClick={onClose}>Լավ</Button>
        </div>
      ) : (
        <div className="space-y-5">
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 id="agent-pay-title" className="text-lg font-semibold text-ink">
                {platform ? "Գրանցել AI աշխատակիցների վճարում" : "Վճարել AI աշխատակիցների համար"}
              </h2>
              <p className="mt-1 text-sm text-ink-muted">
                {mode === "day"
                  ? "Ընտրեք օրը. կհաշվենք տարբերությունը, որպեսզի ընտրված աշխատակիցների ժամկետն ավարտվի նույն օրը։ Հետո բոլորի համար կվճարեք միասին՝ մեկ օրում։"
                  : "Յուրաքանչյուրը երկարաձգվում է իր ավարտի օրից (ավարտվածները՝ այսօրվանից)։"}
              </p>
            </div>
            <DialogClose onClick={onClose} />
          </div>

          <div className={styles.modes} role="tablist">
            <button
              type="button"
              role="tab"
              aria-selected={mode === "months"}
              className={cn(styles.mode, mode === "months" && styles.modeOn)}
              onClick={() => {
                setMode("months");
                setMonths((m) => Math.max(1, m));
              }}
            >
              Ամիսներով
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={mode === "day"}
              className={cn(styles.mode, mode === "day" && styles.modeOn)}
              onClick={() => {
                setMode("day");
                setMonths(0);
              }}
            >
              <CalendarCheck className="mr-1 inline h-4 w-4 align-[-3px]" />
              Մեկ վճարման օր
            </button>
          </div>

          <div className="space-y-2">
            <FieldLabel>AI աշխատակիցներ</FieldLabel>
            <div className="flex flex-wrap gap-2">
              {agents.map((agent) => {
                const on = selected.includes(agent.key);
                return (
                  <button
                    key={agent.key}
                    type="button"
                    aria-pressed={on}
                    className={cn(styles.pick, on && styles.pickOn)}
                    style={agentTintStyle(agent.key)}
                    onClick={() => toggle(agent.key)}
                  >
                    <AgentAvatar agentKey={agent.key} size={28} active={on} />
                    <span className="font-medium">{agent.persona.hy}</span>
                    <span className="text-xs text-ink-muted">
                      {agent.endsAt && agent.status !== "off" ? formatDay(agent.endsAt).slice(0, 5) : "նոր"}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>

          {mode === "day" ? (
            <div className="space-y-2">
              <FieldLabel>Ընդհանուր ավարտի օրը</FieldLabel>
              <div className="flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  className={cn(styles.chip, effectiveDay === latestDay && styles.chipOn)}
                  onClick={() => setDay(latestDay)}
                >
                  Ամենաուշ ավարտը · {latestDay.split("-").reverse().slice(0, 2).join(".")}
                </button>
                {subscriptionDay && subscriptionDay >= latestDay ? (
                  <button
                    type="button"
                    className={cn(styles.chip, effectiveDay === subscriptionDay && styles.chipOn)}
                    onClick={() => setDay(subscriptionDay)}
                  >
                    Բաժանորդագրության օրը · {subscriptionDay.split("-").reverse().slice(0, 2).join(".")}
                  </button>
                ) : null}
                <Input
                  type="date"
                  className="w-auto"
                  min={latestDay}
                  value={effectiveDay}
                  onChange={(e) => setDay(e.target.value)}
                />
              </div>
            </div>
          ) : null}

          <div className="flex flex-wrap items-center gap-3">
            <FieldLabel>{mode === "day" ? "Եվս ամիսներ այդ օրից հետո" : "Ամիսներ"}</FieldLabel>
            <div className="flex items-center gap-1">
              <Button
                variant="secondary"
                size="sm"
                aria-label="Պակաս"
                disabled={months <= (mode === "day" ? 0 : 1)}
                onClick={() => setMonths((m) => m - 1)}
              >
                <Minus className="h-4 w-4" />
              </Button>
              <span className="w-10 text-center text-base font-semibold text-ink">{months}</span>
              <Button
                variant="secondary"
                size="sm"
                aria-label="Ավել"
                disabled={months >= 12}
                onClick={() => setMonths((m) => m + 1)}
              >
                <Plus className="h-4 w-4" />
              </Button>
            </div>
          </div>

          <div className="rounded-xl border border-border bg-surface px-1 pb-2 pt-4">
            <TermTimeline items={timeline} target={targetIso} />
          </div>

          {shown && shown.lines.length > 0 ? (
            <ul className="divide-y divide-border rounded-xl border border-border">
              {shown.lines.map((line, index) => {
                const agent = agents.find((a) => a.key === line.agent);
                return (
                  <li
                    key={line.agent}
                    className={cn(styles.line, "flex items-center gap-3 px-3 py-2.5")}
                    style={{ "--i": index } as CSSProperties}
                  >
                    <AgentAvatar agentKey={line.agent} size={32} />
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium text-ink">{agent?.persona.hy ?? line.agent}</p>
                      <p className="text-xs text-ink-muted">
                        {formatDay(line.from)} → {formatDay(line.to)} · {line.days} օր
                      </p>
                    </div>
                    <p className="text-sm font-semibold text-ink">{amd(line.amount)}</p>
                  </li>
                );
              })}
            </ul>
          ) : null}

          {error ? <p className="rounded-md bg-danger-light px-3 py-2 text-sm text-danger">{error}</p> : null}

          {platform ? (
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1">
                <FieldLabel>Գումար, դր.</FieldLabel>
                <Input
                  type="number"
                  min={0}
                  placeholder={String(shown?.total ?? "")}
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                />
              </div>
              <div className="space-y-1">
                <FieldLabel>Նշում</FieldLabel>
                <Input placeholder="Կանխիկ, Idram, փոխանցում…" value={note} onChange={(e) => setNote(e.target.value)} />
              </div>
            </div>
          ) : null}

          <div className={cn(styles.total, "flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3")}>
            <div>
              <p className="text-xs text-ink-muted">Ընդամենը</p>
              <p className="text-2xl font-semibold tracking-tight text-ink">{amd(total)}</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              {covers ? (
                <Button variant="secondary" onClick={() => submit()} disabled={state === "sending" || !shown || shown.lines.length === 0}>
                  Գրել չատում
                </Button>
              ) : null}
              <Button
                onClick={() => submit(covers)}
                disabled={state === "sending" || !shown || shown.lines.length === 0}
              >
                {state === "sending"
                  ? "Ուղարկվում է…"
                  : platform
                    ? "Գրանցել վճարումը"
                    : covers
                      ? "Վճարել հաշվեկշռից"
                      : "Ուղարկել վճարման հայտը"}
              </Button>
            </div>
          </div>
          {!platform && balance !== null ? (
            <p className="text-right text-xs text-ink-muted">
              Հաշվեկշռում՝ {amd(balance)}
              {shown && !covers && shown.total > 0 ? ` · պակասում է ${amd(shown.total - balance)}. համալրելուց հետո հայտը կվճարվի ինքնաշխատ` : ""}
            </p>
          ) : null}
        </div>
      )}
    </Dialog>
  );
}

"use client";

import Link from "next/link";
import { useState, type CSSProperties } from "react";
import { useRouter } from "next/navigation";
import { CalendarCheck, Clock, Sparkles } from "lucide-react";
import type { AgentBillingAgentDto, AgentBillingDto, AgentPaymentDto } from "@/lib/adminApi";
import { Badge, Button, Card, cn, EmptyRow, Input, SectionCard, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import { ConfirmDialog } from "@/ui/dialog/ConfirmDialog";
import { amd, formatDay } from "../subscription/shared";
import { agentTintStyle } from "./AgentAvatar";
import { AgentPayDialog, type PayMode } from "./AgentPayDialog";
import { AgentTermRing } from "./AgentTermRing";
import { TermTimeline } from "./TermTimeline";
import { payable, TERM_LABELS, TERM_TONES } from "./terms";
import styles from "./terms.module.css";

type Pay = { mode: PayMode; selected: string[] } | null;

/**
 * The business's AI team on the subscription page, each agent on its own
 * term: its face in a ring of the days left, a free trial to start, paying
 * for months, and one billing day for all or some of them (the timeline
 * shows where each one ends). The super admin sees the same for a business
 * (`tenantId`) and records the payments: confirms a request or takes one in.
 */
export function AgentTeamBilling({
  billing,
  canAsk,
  tenantId,
  openAgent,
  balance = null,
}: {
  billing: AgentBillingDto;
  /** The owner or the admin of the business: may start trials and ask to pay. */
  canAsk: boolean;
  /** The platform level: the super admin records payments for this business. */
  tenantId?: number;
  /** Opens paying for this agent (a link from its card on the team page). */
  openAgent?: string | null;
  /** The business's balance: paying from it at once when it covers the total. */
  balance?: number | null;
}) {
  const platform = tenantId !== undefined;
  const acts = canAsk || platform;
  const payableAgents = billing.agents.filter((agent) => payable(agent));
  const onTeam = billing.agents.filter(
    (agent) => agent.status === "trial" || agent.status === "active" || agent.status === "expired",
  );
  const [pay, setPay] = useState<Pay>(
    openAgent && payableAgents.some((a) => a.key === openAgent) && acts ? { mode: "months", selected: [openAgent] } : null,
  );
  const [opened, setOpened] = useState(0);

  function open(next: NonNullable<Pay>) {
    setOpened((n) => n + 1);
    setPay(next);
  }

  const base = platform ? `/api/admin/tenants/${tenantId}/agent-payments` : "/api/admin/agents/billing";

  return (
    <div id="agents" className="scroll-mt-20">
      <SectionCard
        title="AI աշխատակիցներ"
        description={
          platform
            ? "Յուրաքանչյուր AI աշխատակից ունի իր ժամկետը. փորձաշրջան, վճարված ամիսներ։ Հաստատեք բիզնեսի հայտը կամ գրանցեք վճարում։"
            : "Յուրաքանչյուր AI աշխատակից աշխատում է իր ժամկետով. առաջին 7 օրն անվճար է, հետո՝ ամսական վճար։ Կարող եք բոլորի ժամկետը բերել մեկ օրի և վճարել միասին։"
        }
        actions={
          <div className="flex flex-wrap items-center gap-3">
            {acts && payableAgents.length > 0 && onTeam.length > 0 ? (
              <Button
                size="sm"
                onClick={() => open({ mode: "day", selected: onTeam.filter((a) => payable(a)).map((a) => a.key) })}
              >
                <CalendarCheck className="h-4 w-4" />
                Մեկ վճարման օր
              </Button>
            ) : null}
            {platform ? null : (
              <Link href="/admin/employees?tab=agents" className="text-sm font-medium text-primary hover:underline">
                Ծանոթանալ թիմին
              </Link>
            )}
          </div>
        }
      >
        <div className="space-y-5">
          {billing.request ? (
            <PendingRequest request={billing.request} agents={billing.agents} tenantId={tenantId} canCancel={acts} />
          ) : null}

          {onTeam.length > 0 ? (
            <Card className="space-y-2">
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="text-sm font-semibold text-ink">Ավարտի օրերը</h3>
                <p className="text-xs text-ink-muted">
                  {new Set(onTeam.map((a) => (a.endsAt ? formatDay(a.endsAt) : ""))).size === 1 && onTeam.length > 1
                    ? "Բոլորն ավարտվում են նույն օրը. վճարում եք մեկ անգամ։"
                    : "Յուրաքանչյուրի վճարման օրը տարբեր է։"}
                </p>
              </div>
              <TermTimeline items={onTeam.map((a) => ({ key: a.key, name: a.persona.hy, endsAt: a.endsAt }))} />
            </Card>
          ) : null}

          <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {billing.agents.map((agent, index) => (
              <TermCard
                key={agent.key}
                agent={agent}
                index={index}
                canAsk={acts}
                platform={platform}
                onPay={() => open({ mode: "months", selected: [agent.key] })}
              />
            ))}
          </ul>

          {platform ? <PaymentsTable payments={billing.payments} agents={billing.agents} tenantId={tenantId!} /> : null}
        </div>
      </SectionCard>

      {acts && pay ? (
        <AgentPayDialog
          key={opened}
          open
          onClose={() => setPay(null)}
          agents={payableAgents}
          initialMode={pay.mode}
          initialSelected={pay.selected}
          subscriptionEndsAt={billing.subscriptionEndsAt}
          quoteUrl={`${base}/quote`}
          submitUrl={platform ? base : `${base}/request`}
          platform={platform}
          balance={platform ? null : balance}
        />
      ) : null}
    </div>
  );
}

/** One agent: its ring, where its term stands and what can be done about it. */
function TermCard({
  agent,
  index,
  canAsk,
  platform,
  onPay,
}: {
  agent: AgentBillingAgentDto;
  index: number;
  canAsk: boolean;
  platform: boolean;
  onPay: () => void;
}) {
  const fraction =
    agent.status === "included" ? 1 : agent.span > 0 && agent.daysLeft !== null ? Math.min(1, agent.daysLeft / agent.span) : 0;

  return (
    <li
      className={cn(styles.card, "flex flex-col gap-4 rounded-xl border border-border bg-surface p-4")}
      style={{ ...agentTintStyle(agent.key), "--i": index } as CSSProperties}
    >
      <div className="relative flex items-center gap-4">
        <AgentTermRing agentKey={agent.key} term={agent} size={104} index={index} title={agent.label} />
        <div className="min-w-0 flex-1 space-y-1.5">
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-base font-semibold text-ink">{agent.persona.hy}</p>
            <Badge tone={agent.ending ? "warning" : TERM_TONES[agent.status]}>
              {agent.ending ? "Ավարտվում է" : TERM_LABELS[agent.status]}
            </Badge>
          </div>
          <p className="truncate text-sm text-[var(--tint)]">{agent.role.hy}</p>
          <p className="text-xs text-ink-muted">{termLine(agent)}</p>
          {agent.status === "off" ? null : (
            <div className={styles.bar}>
              <div className={styles.barFill} style={{ width: `${Math.max(2, fraction * 100)}%` }} />
            </div>
          )}
        </div>
      </div>

      <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
        <p className="text-sm">
          {agent.status === "included" ? (
            <span className="text-success">Անժամկետ</span>
          ) : (
            <>
              <span className="font-semibold text-ink">{amd(agent.price)}</span>
              <span className="text-ink-muted"> / ամիս</span>
            </>
          )}
        </p>
        {canAsk && agent.status !== "included" ? (
          agent.status === "off" && agent.trialAvailable && !platform ? (
            <TrialButton agent={agent} />
          ) : (
            <Button size="sm" variant={agent.status === "expired" || agent.ending ? "primary" : "secondary"} onClick={onPay}>
              {platform ? "Գրանցել վճարում" : agent.status === "off" ? "Միացնել" : "Երկարացնել"}
            </Button>
          )
        ) : null}
      </div>
    </li>
  );
}

function termLine(agent: AgentBillingAgentDto): string {
  switch (agent.status) {
    case "included":
      return "Ներառված է փաթեթում, ժամկետ չունի";
    case "trial":
      return `Անվճար մինչև ${formatDay(agent.endsAt)}`;
    case "active":
      return `Վճարված է մինչև ${formatDay(agent.endsAt)}`;
    case "expired":
      return agent.endsAt ? `Ավարտվել է ${formatDay(agent.endsAt)}. հիշողությունը պահպանված է` : "Ժամկետ չկա";
    default:
      return agent.trialAvailable ? `Առաջին ${agent.trialDays} օրն անվճար է` : "Փորձաշրջանն օգտագործված է";
  }
}

/** Starts the agent's free trial at once (once per agent), after «are you sure?». */
export function TrialButton({
  agent,
  size = "sm",
}: {
  agent: { key: string; persona: { hy: string }; trialDays: number; price: number };
  size?: "sm" | "md";
}) {
  const router = useRouter();
  const [state, setState] = useState<"idle" | "asking" | "sending" | "error">("idle");
  const [message, setMessage] = useState<string | null>(null);

  async function start() {
    setState("sending");
    const response = await fetch(`/api/admin/agents/${agent.key}/trial`, { method: "POST" }).catch(() => null);
    if (!response?.ok) {
      const json = (await response?.json().catch(() => null)) as { message?: string } | null;
      setMessage(json?.message ?? "Չհաջողվեց միացնել։ Փորձեք կրկին։");
      setState("error");
      return;
    }
    setState("idle");
    router.refresh();
  }

  return (
    <>
      <Button size={size} onClick={() => setState("asking")} disabled={state === "sending"}>
        <Sparkles className="h-4 w-4" />
        Փորձել {agent.trialDays} օր անվճար
      </Button>
      <ConfirmDialog
        open={state === "asking" || state === "sending" || state === "error"}
        content={{
          title: `Սկսե՞լ ${agent.persona.hy}ի անվճար փորձաշրջանը`,
          description: `${agent.persona.hy}ն հենց հիմա կմիանա ձեր թիմին ${agent.trialDays} օրով։ Փորձաշրջանը տրվում է մեկ անգամ. դրանից հետո կարող եք շարունակել ամսական վճարով։`,
          details: [
            { label: "Փորձաշրջան", value: `${agent.trialDays} օր անվճար` },
            { label: "Հետո", value: `${amd(agent.price)}/ամիս` },
          ],
          confirmLabel: "Այո, սկսել",
        }}
        pending={state === "sending"}
        error={state === "error" ? message : null}
        onConfirm={start}
        onCancel={() => setState("idle")}
      />
    </>
  );
}

/** The open request to pay: waiting for Phay; the super admin confirms it once the money came. */
function PendingRequest({
  request,
  agents,
  tenantId,
  canCancel,
}: {
  request: AgentPaymentDto;
  agents: AgentBillingAgentDto[];
  tenantId?: number;
  canCancel: boolean;
}) {
  const router = useRouter();
  const [amount, setAmount] = useState(String(request.amount));
  const [note, setNote] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const platform = tenantId !== undefined;
  const name = (key: string) => agents.find((a) => a.key === key)?.persona.hy ?? key;

  async function call(url: string, method: string, body?: object) {
    setPending(true);
    setError(null);
    const response = await fetch(url, {
      method,
      headers: { "Content-Type": "application/json" },
      body: body ? JSON.stringify(body) : undefined,
    }).catch(() => null);
    setPending(false);
    if (!response?.ok) {
      const json = (await response?.json().catch(() => null)) as { message?: string } | null;
      setError(json?.message ?? "Չհաջողվեց։");
      return;
    }
    router.refresh();
  }

  const cancelUrl = platform
    ? `/api/admin/tenants/${tenantId}/agent-payments/${request.id}`
    : `/api/admin/agents/billing/request/${request.id}`;

  return (
    <div className={cn(styles.pending, "space-y-3 rounded-xl border border-warning/40 p-4")}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="flex items-center gap-2 text-sm font-semibold text-ink">
          <Clock className="h-4 w-4 text-warning" />
          Վճարման հայտ · {platform ? "սպասում է ձեր հաստատմանը" : "սպասում է Phay-ի հաստատմանը"}
        </p>
        <p className="text-lg font-semibold text-ink">{amd(request.amount)}</p>
      </div>
      <ul className="flex flex-wrap gap-2 text-xs">
        {request.lines.map((line) => (
          <li key={line.agent} className="rounded-full bg-surface px-3 py-1 text-ink" style={agentTintStyle(line.agent)}>
            <span className="font-medium text-[var(--tint)]">{name(line.agent)}</span> · մինչև {formatDay(line.to)} ·{" "}
            {amd(line.amount)}
          </li>
        ))}
      </ul>
      <p className="text-xs text-ink-muted">
        {formatDay(request.createdAt)}
        {request.requestedBy ? ` · ${request.requestedBy}` : ""}
      </p>
      {error ? <p className="text-sm text-danger">{error}</p> : null}
      {platform ? (
        <div className="flex flex-wrap items-end gap-2">
          <Input
            type="number"
            min={0}
            className="w-32"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            aria-label="Գումար"
          />
          <Input className="w-48" placeholder="Նշում (Idram…)" value={note} onChange={(e) => setNote(e.target.value)} />
          <Button
            size="sm"
            disabled={pending}
            onClick={() =>
              call(`/api/admin/tenants/${tenantId}/agent-payments/${request.id}/confirm`, "POST", {
                amount: amount === "" ? null : Number(amount),
                note: note.trim() || null,
              })
            }
          >
            Վճարումը ստացված է
          </Button>
          <Button size="sm" variant="ghost" disabled={pending} onClick={() => call(cancelUrl, "DELETE")}>
            Չեղարկել
          </Button>
        </div>
      ) : canCancel ? (
        <Button size="sm" variant="ghost" disabled={pending} onClick={() => call(cancelUrl, "DELETE")}>
          Չեղարկել հայտը
        </Button>
      ) : null}
    </div>
  );
}

/** Agent payments of the business (super admin): the latest can be taken back. */
function PaymentsTable({
  payments,
  agents,
  tenantId,
}: {
  payments: AgentPaymentDto[];
  agents: AgentBillingAgentDto[];
  tenantId: number;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const name = (key: string) => agents.find((a) => a.key === key)?.persona.hy ?? key;

  async function remove(payment: AgentPaymentDto) {
    if (!confirm(`Հեռացնե՞լ ${amd(payment.amount)} վճարումը։ AI աշխատակիցների ժամկետը կվերադառնա նախկինին։`)) return;
    setPending(true);
    await fetch(`/api/admin/tenants/${tenantId}/agent-payments/${payment.id}`, { method: "DELETE" }).catch(() => null);
    setPending(false);
    router.refresh();
  }

  return (
    <section className="space-y-2">
      <h3 className="text-sm font-semibold text-ink">AI աշխատակիցների վճարումներ</h3>
      <TableWrap>
        <Thead>
          <Th>Ամսաթիվ</Th>
          <Th>Գումար</Th>
          <Th>Ում համար</Th>
          <Th>Նշում</Th>
          <Th>Գրանցել է</Th>
          <Th />
        </Thead>
        <tbody>
          {payments.map((payment, index) => (
            <Tr key={payment.id}>
              <Td>{formatDay(payment.paidAt)}</Td>
              <Td className="font-medium">{amd(payment.amount)}</Td>
              <Td className="text-ink-muted">
                {payment.lines.map((line) => `${name(line.agent)} → ${formatDay(line.to)}`).join(", ")}
              </Td>
              <Td className="text-ink-muted">{payment.note ?? "—"}</Td>
              <Td className="text-ink-muted">{payment.recordedBy ?? "—"}</Td>
              <Td className="whitespace-nowrap text-right">
                {index === 0 ? (
                  <Button variant="ghost" size="sm" disabled={pending} onClick={() => remove(payment)}>
                    Հեռացնել
                  </Button>
                ) : null}
              </Td>
            </Tr>
          ))}
          {payments.length === 0 ? <EmptyRow colSpan={6}>Վճարումներ դեռ չկան։</EmptyRow> : null}
        </tbody>
      </TableWrap>
    </section>
  );
}

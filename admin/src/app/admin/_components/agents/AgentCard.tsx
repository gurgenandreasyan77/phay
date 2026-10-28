"use client";

import Link from "next/link";
import { useState, type CSSProperties } from "react";
import { useRouter } from "next/navigation";
import { BookOpen, Check, Info, MessageCircle } from "lucide-react";
import type { AgentDto } from "@/lib/adminApi";
import { Badge, Button, buttonClasses, cn, Textarea } from "@/ui";
import { Dialog, DialogClose } from "@/ui/dialog/Dialog";
import { amd } from "../subscription/shared";
import { AgentAvatar, agentTintStyle } from "./AgentAvatar";
import { AgentJoinAction } from "./AgentJoinAction";
import { AgentTermRing } from "./AgentTermRing";
import { TERM_LABELS, TERM_TONES } from "./terms";
import styles from "./agents.module.css";

/** What a request for an agent (one inside the plan) says before it goes to the chat with Phay. */
export function agentRequestConfirm(agent: AgentDto) {
  return {
    title: `Ավելացնե՞լ «${agent.label}» AI աշխատակցին ձեր թիմին`,
    description: agent.included
      ? "Հայտը կուղարկվի Phay-ի թիմին։ Այս AI աշխատակիցը ներառված է ձեր փաթեթում, ամսական վճարը չի փոխվի։"
      : "Հայտը կուղարկվի Phay-ի թիմին։ Միացնելուց հետո նրա գինը կավելանա ամսական վճարին։",
    details: agent.included ? [] : [{ label: "Գինը", value: `+ ${amd(agent.price)}/ամիս` }],
    confirmLabel: "Ուղարկել հայտը",
  };
}

/**
 * One AI agent as a member of the team: its face (in the ring of its own
 * term once it had one), role, what it does and can't do yet, its price; on
 * the team: talk to it and read or edit what it remembers; not yet or its
 * term ended: start its free trial or pay for it.
 */
export function AgentCard({
  agent,
  canChat,
  canRequest,
  index = 0,
}: {
  agent: AgentDto;
  canChat: boolean;
  canRequest: boolean;
  index?: number;
}) {
  const [notesOpen, setNotesOpen] = useState(false);

  return (
    <article
      id={`agent-${agent.key}`}
      className={cn(styles.card, "flex scroll-mt-24 flex-col rounded-xl border border-border bg-surface")}
      style={{ ...agentTintStyle(agent.key), "--i": index } as CSSProperties}
    >
      <div className={cn(agent.active ? styles.cardWash : styles.cardOff, "flex items-center gap-4 rounded-t-xl px-5 pb-3 pt-5")}>
        {agent.term && agent.term.status !== "off" ? (
          <AgentTermRing agentKey={agent.key} term={agent.term} size={92} index={index} title={agent.label} />
        ) : (
          <AgentAvatar agentKey={agent.key} size={84} active={agent.active} delay={-index * 0.9} title={agent.label} />
        )}
        <div className="min-w-0 space-y-1">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-base font-semibold text-ink">{agent.persona.hy}</h3>
            {agent.term ? (
              <Badge tone={agent.term.ending ? "warning" : TERM_TONES[agent.term.status]}>
                {agent.term.ending ? "Ավարտվում է" : TERM_LABELS[agent.term.status]}
              </Badge>
            ) : agent.active ? (
              <Badge tone="success">Թիմում է</Badge>
            ) : (
              <Badge>Միացված չէ</Badge>
            )}
          </div>
          <p className={cn("text-sm font-medium", agent.active ? "text-[var(--tint)]" : "text-ink-muted")}>{agent.role.hy}</p>
          <p className="text-xs text-ink-faint">
            {agent.role.ru} · {agent.role.en}
          </p>
        </div>
      </div>

      <div className="flex flex-1 flex-col gap-3 px-5 pb-5 pt-2">
        <p className="text-sm text-ink-muted">{agent.description}</p>
        <ul className="space-y-1">
          {agent.skills.map((skill) => (
            <li key={skill} className="flex gap-2 text-sm text-ink">
              <Check className="mt-0.5 h-4 w-4 shrink-0 text-[var(--tint)]" aria-hidden="true" />
              {skill}
            </li>
          ))}
        </ul>
        {agent.limitation ? (
          <p className="flex gap-2 rounded-md bg-surface-secondary px-3 py-2 text-xs text-ink-muted">
            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            {agent.limitation}
          </p>
        ) : null}

        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
          <p className="text-sm text-ink">
            {agent.included ? (
              <span className="text-success">Ներառված է փաթեթում</span>
            ) : (
              <>
                <span className="font-semibold">{amd(agent.price)}</span>
                <span className="text-ink-muted"> / ամիս</span>
              </>
            )}
          </p>
          {agent.active ? (
            <div className="flex flex-wrap gap-2">
              <Button size="sm" variant="secondary" onClick={() => setNotesOpen(true)}>
                <BookOpen className="h-4 w-4" />
                Հիշողություն
              </Button>
              {canChat ? (
                <Link href={`/admin/assistant?agent=${agent.key}`} className={buttonClasses("primary", "sm")}>
                  <MessageCircle className="h-4 w-4" />
                  Զրուցել
                </Link>
              ) : null}
              {canRequest && agent.term?.ending ? <AgentJoinAction agent={agent} /> : null}
            </div>
          ) : canRequest ? (
            <AgentJoinAction agent={agent} />
          ) : (
            <p className="text-xs text-ink-muted">Միացնելու համար դիմեք բիզնեսի սեփականատիրոջը։</p>
          )}
        </div>
      </div>

      {agent.active ? <NotesDialog agent={agent} open={notesOpen} onClose={() => setNotesOpen(false)} /> : null}
    </article>
  );
}

/** What the agent remembers about the business: read, fix or clear it. */
function NotesDialog({ agent, open, onClose }: { agent: AgentDto; open: boolean; onClose: () => void }) {
  const router = useRouter();
  const [notes, setNotes] = useState(agent.notes ?? "");
  const [state, setState] = useState<"idle" | "saving" | "error">("idle");

  async function save() {
    setState("saving");
    const response = await fetch(`/api/admin/agents/${agent.key}/notes`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ notes: notes.trim() === "" ? null : notes }),
    }).catch(() => null);
    if (!response?.ok) {
      setState("error");
      return;
    }
    setState("idle");
    onClose();
    router.refresh();
  }

  return (
    <Dialog open={open} onClose={onClose} labelledBy={`notes-${agent.key}`} className="w-[min(36rem,calc(100vw-2rem))]">
      <div className="space-y-4" style={agentTintStyle(agent.key)}>
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-3">
            <AgentAvatar agentKey={agent.key} size={44} />
            <div>
              <h2 id={`notes-${agent.key}`} className="text-base font-semibold text-ink">
                {agent.persona.hy}ի հիշողությունը
              </h2>
              <p className="text-xs text-ink-muted">
                Զրույցում նա ինքն է ավելացնում այստեղ կարևոր մանրամասներ։ Կարող եք ուղղել կամ ջնջել։
              </p>
            </div>
          </div>
          <DialogClose onClick={onClose} />
        </div>
        <Textarea
          rows={8}
          maxLength={3000}
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="Օր.՝ գրառումները հրապարակում ենք երեկոյան 19:00-ին"
        />
        {state === "error" ? <p className="text-sm text-danger">Չհաջողվեց պահպանել։ Փորձեք կրկին։</p> : null}
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Չեղարկել
          </Button>
          <Button onClick={save} disabled={state === "saving"}>
            {state === "saving" ? "Պահպանվում է…" : "Պահպանել"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

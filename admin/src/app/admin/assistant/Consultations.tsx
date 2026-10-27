"use client";

import { ChevronDown, MessagesSquare } from "lucide-react";
import type { AssistantActionDto } from "@/lib/adminApi";
import { AgentAvatar, agentTintStyle } from "../_components/agents/AgentAvatar";

type Consult = Extract<AssistantActionDto, { type: "consult" }>;

/**
 * Teammates of the AI team the agent asked while answering: who, and on a
 * tap the question and their answer. They only looked things up; changes
 * are the cards under the answer.
 */
export function Consultations({ actions }: { actions: AssistantActionDto[] }) {
  const consults = actions.filter((action): action is Consult => action.type === "consult");
  if (consults.length === 0) return null;

  return (
    <div className="mb-2 space-y-1.5">
      {consults.map((consult, i) => (
        <details
          key={`${consult.agent}-${i}`}
          style={agentTintStyle(consult.agent)}
          className="group rounded-md border border-[var(--tint-soft)] bg-surface/70 text-xs"
        >
          <summary className="flex cursor-pointer list-none items-center gap-2 px-2 py-1.5 text-ink-muted [&::-webkit-details-marker]:hidden">
            <AgentAvatar agentKey={consult.agent} size={22} className="shrink-0" />
            <MessagesSquare className="h-3.5 w-3.5 shrink-0 text-[var(--tint)]" strokeWidth={1.8} />
            <span className="min-w-0 flex-1 truncate">
              Խորհրդակցեց՝ <span className="font-medium text-ink">{consult.name}</span> · {consult.role}
            </span>
            <ChevronDown className="h-3.5 w-3.5 shrink-0 transition-transform group-open:rotate-180" />
          </summary>
          <div className="space-y-2 border-t border-[var(--tint-soft)] px-3 py-2">
            <p className="text-ink-muted">
              <span className="font-medium text-ink">Հարց.</span> {consult.question}
            </p>
            <p className="whitespace-pre-line text-ink">
              <span className="font-medium">{consult.name}.</span> {consult.answer ?? "Չկարողացավ պատասխանել։"}
            </p>
          </div>
        </details>
      ))}
    </div>
  );
}

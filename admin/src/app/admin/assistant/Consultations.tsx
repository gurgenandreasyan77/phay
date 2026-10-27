"use client";

import { ChevronDown, MessagesSquare, Send } from "lucide-react";
import type { AssistantActionDto } from "@/lib/adminApi";
import { AgentAvatar, agentTintStyle } from "../_components/agents/AgentAvatar";

type Teamwork = Extract<AssistantActionDto, { type: "consult" | "delegate" }>;

/**
 * Teammates of the AI team the agent worked with while answering: whom it
 * asked (on a tap the question and their answer) and whom it handed work
 * to (the task and their report; what they made is under the answer).
 */
export function Consultations({ actions }: { actions: AssistantActionDto[] }) {
  const consults = actions.filter((action): action is Teamwork => action.type === "consult" || action.type === "delegate");
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
            {consult.type === "delegate" ? (
              <Send className="h-3.5 w-3.5 shrink-0 text-[var(--tint)]" strokeWidth={1.8} />
            ) : (
              <MessagesSquare className="h-3.5 w-3.5 shrink-0 text-[var(--tint)]" strokeWidth={1.8} />
            )}
            <span className="min-w-0 flex-1 truncate">
              {consult.type === "delegate" ? "Հանձնարարեց՝ " : "Խորհրդակցեց՝ "}
              <span className="font-medium text-ink">{consult.name}</span> · {consult.role}
            </span>
            <ChevronDown className="h-3.5 w-3.5 shrink-0 transition-transform group-open:rotate-180" />
          </summary>
          <div className="space-y-2 border-t border-[var(--tint-soft)] px-3 py-2">
            <p className="text-ink-muted">
              <span className="font-medium text-ink">{consult.type === "delegate" ? "Առաջադրանք." : "Հարց."}</span>{" "}
              {consult.type === "delegate" ? consult.task : consult.question}
            </p>
            <p className="whitespace-pre-line text-ink">
              <span className="font-medium">{consult.name}.</span>{" "}
              {consult.answer ?? (consult.type === "delegate" ? "Արդյունքը՝ ներքևում։" : "Չկարողացավ պատասխանել։")}
            </p>
          </div>
        </details>
      ))}
    </div>
  );
}

"use client";

import Link from "next/link";
import { ArrowRight, Lock, MessageCircle } from "lucide-react";
import type { AgentDto, AssistantActionDto } from "@/lib/adminApi";
import { buttonClasses, cn } from "@/ui";
import { AgentAvatar, agentTintStyle } from "../_components/agents/AgentAvatar";
import { agentRequestConfirm } from "../_components/agents/AgentCard";
import { RequestButton } from "../subscription/RequestButton";

type Refer = Extract<AssistantActionDto, { type: "refer" }>;

/**
 * The agent whose work the question is: on the team, a button to its chat;
 * not yet, that it isn't available and a button that asks Phay to add it
 * (the request goes to the chat with Phay). Whether the business has it is
 * read now, not when the answer was given.
 */
export function ReferCard({ action, agent, canRequest }: { action: Refer; agent: AgentDto | null; canRequest: boolean }) {
  const active = agent?.active ?? false;

  return (
    <div
      style={agentTintStyle(action.agent)}
      className={cn(
        "flex flex-wrap items-center gap-3 rounded-xl border p-3",
        active ? "border-[var(--tint-soft)] bg-surface" : "border-border bg-surface-secondary",
      )}
    >
      <AgentAvatar agentKey={action.agent} size={44} active={active} className="shrink-0" />
      <div className="min-w-0 flex-1 text-sm">
        <p className="font-medium text-ink">
          {action.name} <span className="font-normal text-[var(--tint)]">· {action.role}</span>
        </p>
        <p className="text-xs text-ink-muted">
          {active ? (
            "Այս հարցով կօգնի այս AI աշխատակիցը"
          ) : (
            <>
              <Lock className="mr-1 inline h-3 w-3 align-[-1px]" strokeWidth={2} />
              Այս մասնագետը ձեզ համար դեռ հասանելի չէ։ Ավելացրեք թիմին, և նա կօգնի։
            </>
          )}
        </p>
        {action.task ? <p className="mt-1 text-xs text-ink">«{action.task}»</p> : null}
      </div>
      {active ? (
        <Link href={`/admin/assistant?agent=${action.agent}`} className={buttonClasses("primary", "sm")}>
          <MessageCircle className="h-4 w-4" />
          Բացել զրույցը
        </Link>
      ) : agent && canRequest ? (
        <div className="flex flex-wrap items-center gap-2">
          <RequestButton
            body={{ kind: "agent", agent: agent.key }}
            label="Ավելացնել թիմին"
            confirm={agentRequestConfirm(agent)}
            primary
          />
          <Link
            href={`/admin/subscription#agents`}
            className="inline-flex items-center gap-1 text-xs text-ink-muted underline-offset-2 hover:underline"
          >
            Գինը և մանրամասներ
            <ArrowRight className="h-3 w-3" />
          </Link>
        </div>
      ) : (
        <p className="text-xs text-ink-muted">Միացնելու համար դիմեք բիզնեսի սեփականատիրոջը։</p>
      )}
    </div>
  );
}

"use client";

import Link from "next/link";
import { ArrowRight, Lock, MessageCircle, Play } from "lucide-react";
import type { AgentDto, AssistantActionDto } from "@/lib/adminApi";
import { buttonClasses, cn } from "@/ui";
import { AgentAvatar, agentTintStyle } from "../_components/agents/AgentAvatar";
import { agentRequestConfirm } from "../_components/agents/AgentCard";
import { RequestButton } from "../subscription/RequestButton";

type Refer = Extract<AssistantActionDto, { type: "refer" }>;

/**
 * The agent whose work the question is, offered because the business
 * doesn't have it: that it isn't available and a button that asks Phay to
 * add it (the request goes to the chat with Phay). Once it is on the team,
 * a button to go on in this chat: the agent then hands the work over
 * itself. Whether the business has it is read now, not when the answer was
 * given.
 */
export function ReferCard({
  action,
  agent,
  canRequest,
  onContinue,
}: {
  action: Refer;
  agent: AgentDto | null;
  canRequest: boolean;
  onContinue?: (message: string) => void;
}) {
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
            "Արդեն ձեր թիմում է։ Շարունակեք այստեղ, և աշխատանքը կփոխանցվի նրան։"
          ) : (
            <>
              <Lock className="mr-1 inline h-3 w-3 align-[-1px]" strokeWidth={2} />
              Այս մասնագետը ձեզ համար դեռ հասանելի չէ։ Ավելացրեք թիմին, հետո վերադարձեք այս զրույցին՝ կշարունակենք։
            </>
          )}
        </p>
        {action.task ? <p className="mt-1 text-xs text-ink">«{action.task}»</p> : null}
      </div>
      {active && onContinue ? (
        <button type="button" onClick={() => onContinue(continueMessage(action))} className={buttonClasses("primary", "sm")}>
          <Play className="h-4 w-4" />
          Շարունակել
        </button>
      ) : active ? (
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

/** What the owner says to go on once the agent offered is on the team. */
function continueMessage(action: Refer): string {
  const task = action.task ? ` Շարունակիր՝ «${action.task}»։` : " Շարունակիր։";
  return `${action.role}՝ ${action.name}, արդեն մեր թիմում է։${task}`;
}

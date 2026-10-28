"use client";

import Link from "next/link";
import { CreditCard } from "lucide-react";
import type { AgentDto } from "@/lib/adminApi";
import { buttonClasses } from "@/ui";
import { RequestButton } from "../../subscription/RequestButton";
import { TrialButton } from "./AgentTeamBilling";

/**
 * How an agent that doesn't work now gets (back) on the team: its free
 * trial once, then paying for it on the subscription page (its own term);
 * one inside the plan is asked of Phay, who adds it.
 */
export function AgentJoinAction({ agent, primary }: { agent: AgentDto; primary?: boolean }) {
  const term = agent.term;

  if (agent.included || !term) {
    return (
      <RequestButton
        body={{ kind: "agent", agent: agent.key }}
        label="Ավելացնել թիմին"
        primary={primary}
        confirm={{
          title: `Ավելացնե՞լ «${agent.label}» AI աշխատակցին ձեր թիմին`,
          description: "Հայտը կուղարկվի Phay-ի թիմին։ Այս AI աշխատակիցը ներառված է ձեր փաթեթում, ամսական վճարը չի փոխվի։",
          confirmLabel: "Ուղարկել հայտը",
        }}
      />
    );
  }

  if (term.status === "off" && term.trialAvailable) {
    return <TrialButton agent={{ key: agent.key, persona: agent.persona, trialDays: term.trialDays, price: agent.price }} />;
  }

  return (
    <Link
      href={`/admin/subscription?agent=${agent.key}#agents`}
      className={buttonClasses(primary || term.status === "expired" ? "primary" : "secondary", "sm")}
    >
      <CreditCard className="h-4 w-4" />
      {term.status === "off" ? "Միացնել վճարով" : "Երկարացնել"}
    </Link>
  );
}

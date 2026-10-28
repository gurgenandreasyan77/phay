import type { AgentDto } from "@/lib/adminApi";
import { Card } from "@/ui";
import { AgentCard } from "../_components/agents/AgentCard";

/** An agent the business doesn't have yet: what it would do, and how to add it. */
export function AgentLocked({ agent, canRequest }: { agent: AgentDto; canRequest: boolean }) {
  return (
    <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
      <AgentCard agent={agent} canChat={false} canRequest={canRequest} />
      <Card className="space-y-3">
        <p className="text-sm font-semibold text-ink">Ինչպես կսկսի աշխատել</p>
        <ol className="list-decimal space-y-2 pl-5 text-sm text-ink-muted">
          <li>
            {agent.term?.trialAvailable
              ? `Սկսեք ${agent.term.trialDays} օր անվճար փորձաշրջանը. աշխատակիցն անմիջապես կմիանա թիմին։`
              : "Վճարեք նրա ամսականը «Բաժանորդագրություն» էջում. Phay-ի թիմը կհաստատի վճարումը չատում։"}
          </li>
          <li>Առաջին զրույցում նա կհարցնի ձեր բիզնեսի, հաճախորդների, ոճի և գույների մասին։</li>
          <li>Պատասխանները կպահվեն ձեր բրենդի պրոֆիլում, և ամբողջ AI թիմը կաշխատի ձեր ոճով։</li>
        </ol>
        <p className="text-xs text-ink-faint">Յուրաքանչյուր հարց օգտագործում է AI օգնականի ամսական հարցերից մեկը։</p>
      </Card>
    </div>
  );
}

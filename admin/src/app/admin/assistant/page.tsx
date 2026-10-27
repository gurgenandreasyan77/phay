import { redirect } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { can, isPlatformStaff } from "@/lib/roles";
import {
  getAgents,
  getAssistantConversation,
  getAssistantConversations,
  getAssistantStatus,
  getDataTables,
  getFeatureFlagsAdmin,
  getRecentChats,
} from "@/lib/adminApi";
import { PageHeader } from "@/ui";
import { AgentPool } from "../_components/agents/AgentPool";
import { AgentLocked } from "./AgentLocked";
import { AssistantChat } from "./AssistantChat";
import { lastChatHref } from "./lastChat";
import { SOCIAL_SUGGESTION, SUGGESTIONS } from "./suggestions";

export const dynamic = "force-dynamic";

export default async function AdminAssistantPage(props: PageProps<"/admin/assistant">) {
  const admin = await requirePermission("insights");
  const { c, agent: agentKey, new: fresh } = await props.searchParams;
  const recent = await getRecentChats();
  // Coming back (the menu, the address alone): the chat the person was in.
  // ?new=1 is the assistant's own new chat.
  if (c === undefined && agentKey === undefined && fresh === undefined) {
    const last = lastChatHref(recent);
    if (last) redirect(last);
  }
  // ?c=<id> reopens one of the person's earlier chats; ?agent=<key> talks to an AI agent.
  const openId = typeof c === "string" && /^\d+$/.test(c) ? Number(c) : null;
  const wanted = typeof agentKey === "string" && /^[a-z_-]{1,40}$/.test(agentKey) ? agentKey : null;
  const [status, tables, team, opened, flags] = await Promise.all([
    getAssistantStatus(),
    getDataTables(),
    getAgents(),
    openId ? getAssistantConversation(openId) : null,
    getFeatureFlagsAdmin(),
  ]);
  // A reopened chat stays with its own agent, whatever the address says.
  const agent = team?.agents.find((a) => a.key === (opened ? opened.agent : wanted)) ?? null;
  const conversations = await getAssistantConversations(agent?.key ?? null);
  // A question of this chat still being answered (asked before the person left the page).
  const asking =
    recent?.asking.findLast((a) => (a.agent ?? null) === (agent?.key ?? null) && a.conversationId === (opened?.id ?? null)) ??
    null;
  const canAsk = can(admin, "settings") && !isPlatformStaff(admin);
  // A shop that keeps only sales gets questions about sales.
  const salesOnly = tables.some((t) => t.section === "sales") && !tables.some((t) => t.section === "orders");

  return (
    <div className="space-y-6">
      <PageHeader
        title="AI օգնական"
        description="Հարցրեք ձեր վաճառքների, պատվերների, ծախսերի և շահույթի մասին կամ աշխատեք ձեր AI թիմի հետ. վերլուծաբան, հաշվապահ, կոնտենտ, ռիլսեր, մոնտաժ և դիզայն։ Զրույցները պահվում են «Պատմություն»-ում։ Հաճախորդների անուններն ու հեռախոսները AI-ին չեն փոխանցվում։"
      />
      {team ? <AgentPool agents={team.agents} selected={agent?.key ?? null} /> : null}
      {agent && !agent.active ? (
        <AgentLocked agent={agent} canRequest={team?.canRequest ?? false} />
      ) : (
        <AssistantChat
          suggestions={
            flags.find((f) => f.key === "social")?.active
              ? [...(salesOnly ? SUGGESTIONS.sales : SUGGESTIONS.orders).slice(0, 3), SOCIAL_SUGGESTION]
              : salesOnly
                ? SUGGESTIONS.sales
                : SUGGESTIONS.orders
          }
          configured={status?.configured ?? false}
          initialUsage={
            status ?? {
              used: 0,
              limit: 0,
              planLimit: 0,
              bonus: 0,
              remaining: 0,
              unlimited: false,
              periodStart: new Date().toISOString(),
              resetsAt: new Date().toISOString(),
            }
          }
          plans={status?.offer.plans ?? []}
          // Asking for a plan or more questions goes to the chat with the platform.
          canAsk={canAsk}
          showDetails={isPlatformStaff(admin) || process.env.NODE_ENV !== "production"}
          initialConversations={conversations}
          // Key: another ?c= gives the chat its own state.
          initialConversation={opened}
          asking={asking}
          agent={agent}
          brandComplete={team?.profile.complete ?? true}
          team={team?.agents ?? []}
          canRequest={team?.canRequest ?? false}
          key={`${agent?.key ?? "assistant"}-${opened?.id ?? "new"}-${asking?.startedAt ?? ""}`}
        />
      )}
    </div>
  );
}

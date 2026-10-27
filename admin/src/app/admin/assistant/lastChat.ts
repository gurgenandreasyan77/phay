// Shared by the assistant page (server) and the header (client): no hooks here.
import type { RecentChatsDto } from "@/lib/adminApi";

/** Where a chat opens: its agent and the conversation. */
export function chatHref(agent: string | null, conversationId: number | null): string {
  const params = new URLSearchParams();
  if (agent) params.set("agent", agent);
  if (conversationId) params.set("c", String(conversationId));
  // The assistant's new chat: not the last chat reopened.
  if (!agent && !conversationId) params.set("new", "1");
  return `/admin/assistant?${params}`;
}

/**
 * The chat the person was in last: a question being answered now, or the
 * chat answered or opened last. Null when they haven't talked to anyone.
 */
export function lastChatHref(recent: RecentChatsDto | null): string | null {
  if (!recent) return null;
  const asking = recent.asking.at(-1);
  const chat = recent.chats[0];
  const time = (iso: string | null | undefined) => (iso ? Date.parse(iso) : 0);
  if (asking && (!chat || time(asking.startedAt) >= time(chat.activeAt))) {
    return chatHref(asking.agent, asking.conversationId);
  }
  return chat ? chatHref(chat.agent, chat.id) : null;
}

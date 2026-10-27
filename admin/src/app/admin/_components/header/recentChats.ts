"use client";

import { useCallback, useEffect, useState, useSyncExternalStore } from "react";
import type { AskingChatDto, RecentChatDto, RecentChatsDto } from "@/lib/adminApi";
import { chatHref } from "../../assistant/lastChat";

/** Sent by the chat when a question goes out or its answer comes: the header looks again. */
export const ASSISTANT_EVENT = "phay:assistant-activity";

const POLL_MS = 30_000;
// While an agent is answering the answer is a few seconds away.
const ASKING_POLL_MS = 4_000;

export function announceAssistantActivity() {
  window.dispatchEvent(new Event(ASSISTANT_EVENT));
}

/** A chat shown in the header: being answered, or kept. */
export type ShownChat =
  | ({ kind: "asking"; key: string; href: string } & AskingChatDto)
  | ({ kind: "chat"; key: string; href: string } & RecentChatDto);

/** Questions being answered first, then the chats, latest first; one row a chat. */
export function shownChats(data: RecentChatsDto | null): ShownChat[] {
  if (!data) return [];
  const asking: ShownChat[] = [...data.asking].reverse().map((a, i) => ({
    ...a,
    kind: "asking",
    key: `asking-${a.conversationId ?? `new-${a.agent ?? "assistant"}-${i}`}`,
    href: chatHref(a.agent, a.conversationId),
  }));
  const busy = new Set(data.asking.map((a) => a.conversationId).filter(Boolean));
  const chats: ShownChat[] = data.chats
    .filter((c) => !busy.has(c.id))
    .map((c) => ({ ...c, kind: "chat", key: `chat-${c.id}`, href: chatHref(c.agent, c.id) }));
  return [...asking, ...chats];
}

/** The face on the button: who is answering now, else whose answer is new, else the last one talked to. */
export function featuredChat(chats: ShownChat[]): ShownChat | null {
  return chats.find((c) => c.kind === "asking") ?? chats.find((c) => c.kind === "chat" && c.unread) ?? chats[0] ?? null;
}

/** The person's latest chats, polled like the bell (often while an agent is answering). */
export function useRecentChats(enabled: boolean) {
  const [data, setData] = useState<RecentChatsDto | null>(null);

  const load = useCallback(async () => {
    try {
      const response = await fetch("/api/admin/assistant/recent", { cache: "no-store" });
      if (!response.ok) return;
      const json = (await response.json()) as { data?: RecentChatsDto };
      if (json.data) setData(json.data);
    } catch {
      // A missed poll keeps what was shown.
    }
  }, []);

  const asking = (data?.asking.length ?? 0) > 0;

  useEffect(() => {
    if (!enabled) return;
    const first = setTimeout(load, 0);
    const timer = setInterval(
      () => {
        if (document.visibilityState === "visible") load();
      },
      asking ? ASKING_POLL_MS : POLL_MS,
    );
    // The answer is saved a moment before the chat says so.
    const onActivity = () => setTimeout(load, 300);
    window.addEventListener(ASSISTANT_EVENT, onActivity);
    window.addEventListener("focus", load);
    return () => {
      clearTimeout(first);
      clearInterval(timer);
      window.removeEventListener(ASSISTANT_EVENT, onActivity);
      window.removeEventListener("focus", load);
    };
  }, [enabled, load, asking]);

  return { data, reload: load };
}

/* The chat open on the assistant page right now: the header doesn't offer it again. */
type OpenChat = { agent: string | null; conversationId: number | null } | null;
let openChat: OpenChat = null;
const listeners = new Set<() => void>();

export function setOpenChat(next: OpenChat) {
  if (openChat?.agent === next?.agent && openChat?.conversationId === next?.conversationId) return;
  openChat = next;
  for (const listener of listeners) listener();
}

export function useOpenChat(): OpenChat {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener);
      return () => listeners.delete(listener);
    },
    () => openChat,
    () => null,
  );
}

/** Whether a shown chat is the one open on the page. */
export function isOpen(chat: ShownChat, open: OpenChat): boolean {
  if (!open || (chat.agent ?? null) !== open.agent) return false;
  const id = chat.kind === "asking" ? chat.conversationId : chat.id;
  return id === open.conversationId;
}

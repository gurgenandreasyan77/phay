"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState, type CSSProperties } from "react";
import { MessageCircle } from "lucide-react";
import { cn } from "@/ui";
import { Dialog, DialogClose } from "@/ui/dialog/Dialog";
import { AgentAvatar, AssistantOrb, agentTintStyle } from "../agents/AgentAvatar";
import { timeAgo } from "./notifications";
import type { RecentChatsDto } from "@/lib/adminApi";
import { featuredChat, isOpen, shownChats, useOpenChat, type ShownChat } from "./recentChats";
import styles from "./agentChats.module.css";

function Face({ chat, size }: { chat: ShownChat; size: number }) {
  return chat.agent ? (
    <AgentAvatar agentKey={chat.agent} size={size} talking={chat.kind === "asking"} />
  ) : (
    <AssistantOrb size={size} />
  );
}

function tint(chat: ShownChat): CSSProperties {
  return agentTintStyle(chat.agent ?? "assistant");
}

/** What the button says about its chat, for screen readers and the wide header. */
function status(chat: ShownChat): string {
  if (chat.kind === "asking") return "պատրաստում է պատասխանը…";
  return chat.unread ? "նոր պատասխան" : "վերջին զրույցը";
}

/**
 * The person's chats with the AI team, to come back to one: the agent last
 * talked to (or answering now, or with a new answer) in the header on wide
 * screens and as a floating button at the bottom right on a phone. It opens
 * a list of the latest chats with what was asked and answered; a row opens
 * its chat. AdminShell polls the chats once for both (useRecentChats).
 */
export function AgentChats({ data, variant }: { data: RecentChatsDto | null; variant: "header" | "fab" }) {
  const pathname = usePathname();
  const open = useOpenChat();
  const [listOpen, setListOpen] = useState(false);
  const [lastPathname, setLastPathname] = useState(pathname);
  const ref = useRef<HTMLDivElement>(null);

  if (pathname !== lastPathname) {
    setLastPathname(pathname);
    setListOpen(false);
  }

  useEffect(() => {
    if (!listOpen || variant !== "header") return;
    function handleClick(e: PointerEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setListOpen(false);
    }
    function handleKey(e: KeyboardEvent) {
      if (e.key === "Escape") setListOpen(false);
    }
    document.addEventListener("pointerdown", handleClick);
    document.addEventListener("keydown", handleKey);
    return () => {
      document.removeEventListener("pointerdown", handleClick);
      document.removeEventListener("keydown", handleKey);
    };
  }, [listOpen, variant]);

  const onAssistant = pathname.startsWith("/admin/assistant");
  const chats = shownChats(data);
  // On the assistant page only what needs a look in another chat.
  const offered = onAssistant ? chats.filter((c) => !isOpen(c, open) && (c.kind === "asking" || c.unread)) : chats;
  const featured = featuredChat(offered);
  // The floating button would cover the question box of the chat.
  if (!featured || (variant === "fab" && onAssistant)) return null;

  const unread = chats.filter((c) => c.kind === "chat" && c.unread && !isOpen(c, open)).length;
  const asking = featured.kind === "asking";
  const label = `${featured.name}, ${status(featured)}${unread > 0 ? `. Նոր պատասխաններ՝ ${unread}` : ""}`;
  const list = <ChatList chats={chats} onPick={() => setListOpen(false)} />;

  if (variant === "fab") {
    return (
      <>
        <button
          type="button"
          onClick={() => setListOpen(true)}
          aria-label={label}
          className={cn(styles.fab, asking && styles.asking, unread > 0 && styles.fresh)}
          style={tint(featured)}
        >
          <span className={styles.ring} aria-hidden="true" />
          <span className={styles.face}>
            <Face chat={featured} size={46} />
          </span>
          {unread > 0 ? <span className={styles.badge}>{unread > 9 ? "9+" : unread}</span> : null}
        </button>
        <Dialog open={listOpen} onClose={() => setListOpen(false)} sheet label="Զրույցներ AI թիմի հետ" className="p-0!">
          <div className="flex items-center justify-between border-b border-border py-2 pr-2 pl-4">
            <p className="text-sm font-semibold text-ink">Վերջին զրույցները</p>
            <DialogClose onClick={() => setListOpen(false)} />
          </div>
          {list}
        </Dialog>
      </>
    );
  }

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => setListOpen((v) => !v)}
        aria-label={label}
        aria-expanded={listOpen}
        className={cn(styles.chip, asking && styles.asking, unread > 0 && styles.fresh)}
        style={tint(featured)}
        title={featured.kind === "chat" && featured.question ? `${featured.name}. ${featured.question}` : featured.name}
      >
        <span className={styles.chipFace}>
          <span className={styles.ring} aria-hidden="true" />
          <span className={styles.face}>
            <Face chat={featured} size={30} />
          </span>
        </span>
        <span className="hidden min-w-0 flex-col text-left leading-tight xl:flex">
          <span className="truncate text-xs font-semibold text-ink">{featured.name}</span>
          <span className="truncate text-[11px] text-[var(--tint)]">{status(featured)}</span>
        </span>
        {unread > 0 ? <span className={styles.badge}>{unread > 9 ? "9+" : unread}</span> : null}
      </button>
      {listOpen ? (
        <div className="absolute top-full right-0 z-30 mt-1 w-96 max-w-[calc(100vw-1rem)] overflow-hidden rounded-lg border border-border bg-surface shadow-md">
          <p className="border-b border-border px-4 py-3 text-sm font-semibold text-ink">Վերջին զրույցները</p>
          {list}
        </div>
      ) : null}
    </div>
  );
}

/** The latest chats: who, what was asked, the opening words of the answer. */
function ChatList({ chats, onPick }: { chats: ShownChat[]; onPick: () => void }) {
  return (
    <ul className="overscroll-contain max-h-[min(70dvh,30rem)] divide-y divide-border overflow-y-auto">
      {chats.map((chat) => {
        const fresh = chat.kind === "chat" && chat.unread;
        return (
          <li key={chat.key} style={tint(chat)}>
            <Link
              href={chat.href}
              onClick={onPick}
              className={cn(styles.row, "flex min-h-11 gap-3 px-4 py-3", fresh && styles.rowFresh)}
            >
              <span className="shrink-0">
                <Face chat={chat} size={40} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="flex items-baseline justify-between gap-2">
                  <span className="truncate text-sm text-ink">
                    <span className="font-semibold">{chat.name}</span>
                    <span className="text-[var(--tint)]"> · {chat.role}</span>
                  </span>
                  <span className="shrink-0 text-[11px] text-ink-faint">
                    {timeAgo(chat.kind === "asking" ? chat.startedAt : chat.activeAt)}
                  </span>
                </span>
                {chat.kind === "asking" ? (
                  <>
                    <span className="mt-0.5 block truncate text-xs text-ink-muted">Դուք՝ {chat.question}</span>
                    <span className="mt-1 flex items-center gap-2 text-xs font-medium text-[var(--tint)]">
                      <span className={styles.dots} aria-hidden="true">
                        <span />
                        <span />
                        <span />
                      </span>
                      պատրաստում է պատասխանը…
                    </span>
                  </>
                ) : (
                  <>
                    {chat.question ? (
                      <span className="mt-0.5 block truncate text-xs text-ink-muted">Դուք՝ {chat.question}</span>
                    ) : null}
                    <span className="mt-1 flex items-start gap-2">
                      <span className={cn("line-clamp-2 text-xs", fresh ? "font-medium text-ink" : "text-ink-muted")}>
                        {chat.answer ?? "Պատասխան չկա"}
                      </span>
                      {fresh ? <span className={styles.dot} aria-label="Նոր պատասխան" /> : null}
                    </span>
                  </>
                )}
              </span>
            </Link>
          </li>
        );
      })}
      <li>
        <Link
          href="/admin/assistant?new=1"
          onClick={onPick}
          className="flex min-h-11 items-center justify-center gap-2 px-4 py-2.5 text-xs font-medium text-primary hover:bg-surface-secondary"
        >
          <MessageCircle className="h-3.5 w-3.5" strokeWidth={1.75} />
          Ամբողջ թիմը և նոր զրույց
        </Link>
      </li>
    </ul>
  );
}

"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { Check, CheckCheck, Send } from "lucide-react";
import type { SupportMessageDto } from "@/lib/adminApi";
import { Button, Textarea, cn } from "../ui";

/** How often an open conversation asks for new messages (no realtime yet). */
const POLL_MS = 10_000;

// "18.10, 16:36" in Yerevan time: numeric, so it reads the same in any
// browser (many ship without Armenian month names).
const timeFormat = new Intl.DateTimeFormat("ru-RU", {
  day: "2-digit",
  month: "2-digit",
  hour: "2-digit",
  minute: "2-digit",
  timeZone: "Asia/Yerevan",
});

/**
 * One conversation between a business and the platform team, from either
 * side: `side` says whose messages are "mine" (right, colored). Opening and
 * polling it marks the other side's messages as read on the server.
 */
export function ChatThread({
  url,
  side,
  emptyText,
  onActivity,
  className,
}: {
  /** BFF endpoint: GET lists the messages, POST { body } sends one. */
  url: string;
  side: "business" | "platform";
  emptyText: string;
  /** Called after each load or send, e.g. to refresh unread badges. */
  onActivity?: () => void;
  className?: string;
}) {
  const [messages, setMessages] = useState<SupportMessageDto[] | null>(null);
  const [body, setBody] = useState("");
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<"load" | "send" | null>(null);
  const listRef = useRef<HTMLDivElement>(null);
  const lastIdRef = useRef<number | null>(null);
  const onActivityRef = useRef(onActivity);

  useEffect(() => {
    onActivityRef.current = onActivity;
  }, [onActivity]);

  const load = useCallback(async () => {
    try {
      const response = await fetch(url, { cache: "no-store" });
      if (!response.ok) {
        setError("load");
        return;
      }
      const json = (await response.json()) as { data: SupportMessageDto[] | null };
      setMessages(json.data ?? []);
      setError((current) => (current === "load" ? null : current));
      onActivityRef.current?.();
    } catch {
      setError("load");
    }
  }, [url]);

  useEffect(() => {
    // Initial load, then every POLL_MS while the tab is visible.
    const first = setTimeout(load, 0);
    const timer = setInterval(() => {
      if (document.visibilityState === "visible") load();
    }, POLL_MS);
    return () => {
      clearTimeout(first);
      clearInterval(timer);
    };
  }, [load]);

  // Stick to the newest message when one arrives.
  useEffect(() => {
    const lastId = messages?.at(-1)?.id ?? null;
    if (lastId !== lastIdRef.current && listRef.current) {
      listRef.current.scrollTop = listRef.current.scrollHeight;
    }
    lastIdRef.current = lastId;
  }, [messages]);

  async function send() {
    const text = body.trim();
    if (!text || sending) return;
    setSending(true);
    setError(null);

    try {
      const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ body: text }),
      });
      if (!response.ok) {
        setError("send");
        return;
      }
      const json = (await response.json()) as { data: SupportMessageDto };
      setMessages((current) => [...(current ?? []), json.data]);
      setBody("");
      onActivityRef.current?.();
    } catch {
      setError("send");
    } finally {
      setSending(false);
    }
  }

  const isMine = (message: SupportMessageDto) => message.fromPlatform === (side === "platform");

  return (
    <div className={cn("flex h-[32rem] flex-col rounded-lg border border-border bg-surface", className)}>
      <div ref={listRef} className="flex-1 space-y-3 overflow-y-auto p-4">
        {messages === null && error !== "load" ? (
          <p className="text-center text-sm text-ink-muted">Բեռնվում է…</p>
        ) : null}
        {messages?.length === 0 ? <p className="py-10 text-center text-sm text-ink-muted">{emptyText}</p> : null}
        {messages?.map((message) => {
          const mine = isMine(message);
          return (
            <div key={message.id} className={cn("flex", mine ? "justify-end" : "justify-start")}>
              <div
                className={cn(
                  "max-w-[80%] rounded-lg px-3 py-2 text-sm",
                  mine ? "bg-primary text-primary-foreground" : "bg-surface-secondary text-ink",
                )}
              >
                {/* No author: a message the platform sent by itself (e.g. about the subscription). */}
                {!mine && (message.authorName || message.fromPlatform) ? (
                  <p className="mb-0.5 text-xs font-medium text-ink-muted">
                    {message.fromPlatform
                      ? message.authorName
                        ? `${message.authorName} · Servio`
                        : "Servio"
                      : message.authorName}
                  </p>
                ) : null}
                <p className="whitespace-pre-wrap break-words">{message.body}</p>
                <p
                  className={cn(
                    "mt-1 flex items-center justify-end gap-1 text-[11px]",
                    mine ? "text-primary-foreground/75" : "text-ink-faint",
                  )}
                >
                  {message.createdAt ? timeFormat.format(new Date(message.createdAt)) : null}
                  {mine ? (
                    message.readAt ? (
                      <CheckCheck className="h-3.5 w-3.5" strokeWidth={2} aria-label="Կարդացվել է" />
                    ) : (
                      <Check className="h-3.5 w-3.5" strokeWidth={2} aria-label="Ուղարկված է" />
                    )
                  ) : null}
                </p>
              </div>
            </div>
          );
        })}
      </div>

      <form
        onSubmit={(e) => {
          e.preventDefault();
          send();
        }}
        className="border-t border-border p-3"
      >
        {error ? (
          <p className="pb-2 text-xs text-danger">
            {error === "load" ? "Չհաջողվեց բեռնել հաղորդագրությունները։" : "Չհաջողվեց ուղարկել։ Փորձեք կրկին։"}
          </p>
        ) : null}
        <div className="flex items-end gap-2">
          <Textarea
            value={body}
            rows={2}
            maxLength={4000}
            placeholder="Գրեք հաղորդագրություն… (Enter՝ ուղարկել, Shift+Enter՝ նոր տող)"
            onChange={(e) => setBody(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey && !e.nativeEvent.isComposing) {
                e.preventDefault();
                send();
              }
            }}
            className="resize-none"
          />
          <Button type="submit" disabled={sending || !body.trim()} aria-label="Ուղարկել">
            <Send className="h-4 w-4" strokeWidth={1.75} />
            <span className="hidden sm:inline">Ուղարկել</span>
          </Button>
        </div>
      </form>
    </div>
  );
}

"use client";

import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import {
  AtSign,
  BadgeCheck,
  Bell,
  Bot,
  CalendarDays,
  CheckCheck,
  ClipboardList,
  Inbox,
  MessageCircle,
  TreePalm,
} from "lucide-react";
import { cn } from "@/ui";
import { timeAgo, useNotifications, type AdminNotificationDto } from "./notifications";

const ICONS = {
  new_lead: ClipboardList,
  feedback: Inbox,
  support_message: MessageCircle,
  subscription: BadgeCheck,
  today_orders: CalendarDays,
  social: AtSign,
  agent: Bot,
  team: TreePalm,
} as const;

export function NotificationBell({ enabled }: { enabled: boolean }) {
  const router = useRouter();
  const { items, unread, reload, markRead, markAllRead } = useNotifications(enabled);
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    function handleClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    function handleKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    document.addEventListener("pointerdown", handleClick);
    document.addEventListener("keydown", handleKey);
    return () => {
      document.removeEventListener("pointerdown", handleClick);
      document.removeEventListener("keydown", handleKey);
    };
  }, [open]);

  function toggle() {
    if (!open) reload();
    setOpen((v) => !v);
  }

  function openNotification(notification: AdminNotificationDto) {
    markRead(notification.id);
    setOpen(false);
    if (notification.url) router.push(notification.url);
  }

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={toggle}
        aria-label={unread > 0 ? `Ծանուցումներ, ${unread} չկարդացված` : "Ծանուցումներ"}
        aria-expanded={open}
        className="relative flex h-11 w-11 items-center justify-center rounded-md text-ink-muted hover:bg-surface-secondary hover:text-ink active:bg-surface-secondary sm:h-9 sm:w-9"
      >
        <Bell className="h-4.5 w-4.5" strokeWidth={1.75} />
        {unread > 0 ? (
          <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold leading-none text-white">
            {unread > 9 ? "9+" : unread}
          </span>
        ) : null}
      </button>

      {open ? (
        <div className="fixed inset-x-3 top-[calc(3.5rem+env(safe-area-inset-top,0px))] z-30 mt-1 rounded-lg border border-border bg-surface shadow-md sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:w-96">
          <div className="flex items-center justify-between border-b border-border px-4 py-3">
            <p className="text-sm font-semibold text-ink">Ծանուցումներ</p>
            {unread > 0 ? (
              <button
                type="button"
                onClick={markAllRead}
                className="flex items-center gap-1 text-xs text-primary hover:underline"
              >
                <CheckCheck className="h-3.5 w-3.5" strokeWidth={1.75} />
                Նշել բոլորը կարդացված
              </button>
            ) : null}
          </div>

          {items.length === 0 ? (
            <p className="px-4 py-8 text-center text-sm text-ink-muted">Ծանուցումներ դեռ չկան։</p>
          ) : (
            <ul className="overscroll-contain max-h-[min(70dvh,28rem)] divide-y divide-border overflow-y-auto">
              {items.map((notification) => {
                const Icon = ICONS[notification.type as keyof typeof ICONS] ?? Bell;
                return (
                  <li key={notification.id}>
                    <button
                      type="button"
                      onClick={() => openNotification(notification)}
                      className={cn(
                        "flex w-full gap-3 px-4 py-3 text-left hover:bg-surface-secondary",
                        !notification.read && "bg-primary-light/40",
                      )}
                    >
                      <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-surface-secondary text-ink-muted">
                        <Icon className="h-4 w-4" strokeWidth={1.75} />
                      </span>
                      <span className="min-w-0 flex-1">
                        <span className="flex items-start justify-between gap-2">
                          <span className={cn("text-sm text-ink", !notification.read && "font-semibold")}>
                            {notification.title}
                          </span>
                          {!notification.read ? (
                            <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" aria-label="Չկարդացված" />
                          ) : null}
                        </span>
                        {notification.body ? (
                          <span className="mt-0.5 line-clamp-2 block text-xs text-ink-muted">{notification.body}</span>
                        ) : null}
                        <span className="mt-1 block text-[11px] text-ink-faint">
                          {notification.scope === "platform" && notification.tenant ? `${notification.tenant.name} · ` : ""}
                          {timeAgo(notification.createdAt)}
                        </span>
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      ) : null}
    </div>
  );
}

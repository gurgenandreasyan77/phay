"use client";

import { useCallback, useEffect, useState } from "react";

export type NotificationType = "new_lead" | "feedback" | "support_message" | "subscription" | "today_orders";

export type AdminNotificationDto = {
  id: number;
  type: NotificationType | string;
  scope: "business" | "platform";
  title: string;
  body: string | null;
  url: string | null;
  tenant: { id: number; name: string } | null;
  read: boolean;
  createdAt: string | null;
};

const POLL_MS = 30_000;
// Sent by the support chat when it is opened: its message notifications are read now.
const SUPPORT_EVENT = "phay:support-activity";

/** The bell's notifications, polled like the chat's unread badges. */
export function useNotifications(enabled: boolean) {
  const [items, setItems] = useState<AdminNotificationDto[]>([]);
  const [unread, setUnread] = useState(0);

  const load = useCallback(async () => {
    try {
      const response = await fetch("/api/admin/notifications", { cache: "no-store" });
      if (!response.ok) return;
      const json = (await response.json()) as { data?: AdminNotificationDto[]; meta?: { unread?: number } };
      setItems(json.data ?? []);
      setUnread(json.meta?.unread ?? 0);
    } catch {
      // A missed poll just keeps the old list.
    }
  }, []);

  useEffect(() => {
    if (!enabled) return;
    const first = setTimeout(load, 0);
    const timer = setInterval(() => {
      if (document.visibilityState === "visible") load();
    }, POLL_MS);
    const onSupport = () => setTimeout(load, 500);
    window.addEventListener(SUPPORT_EVENT, onSupport);
    window.addEventListener("focus", load);
    return () => {
      clearTimeout(first);
      clearInterval(timer);
      window.removeEventListener(SUPPORT_EVENT, onSupport);
      window.removeEventListener("focus", load);
    };
  }, [enabled, load]);

  async function markRead(id: number) {
    const target = items.find((n) => n.id === id);
    if (!target || target.read) return;
    setItems((list) => list.map((n) => (n.id === id ? { ...n, read: true } : n)));
    setUnread((count) => Math.max(0, count - 1));
    await fetch(`/api/admin/notifications/${id}/read`, { method: "POST" }).catch(() => null);
  }

  async function markAllRead() {
    setItems((list) => list.map((n) => ({ ...n, read: true })));
    setUnread(0);
    await fetch("/api/admin/notifications/read-all", { method: "POST" }).catch(() => null);
    load();
  }

  return { items, unread, reload: load, markRead, markAllRead };
}

/** "5 րոպե առաջ", "երեկ 14:30", "12.10.2026". */
export function timeAgo(iso: string | null, now = Date.now()): string {
  if (!iso) return "";
  const date = new Date(iso);
  const minutes = Math.floor((now - date.getTime()) / 60_000);
  const time = date.toLocaleTimeString("hy-AM", { hour: "2-digit", minute: "2-digit", hour12: false });
  if (minutes < 1) return "հենց հիմա";
  if (minutes < 60) return `${minutes} րոպե առաջ`;
  const today = new Date(now);
  if (date.toDateString() === today.toDateString()) return `${Math.floor(minutes / 60)} ժամ առաջ`;
  const yesterday = new Date(now - 86_400_000);
  if (date.toDateString() === yesterday.toDateString()) return `երեկ ${time}`;
  const day = String(date.getDate()).padStart(2, "0");
  const month = String(date.getMonth() + 1).padStart(2, "0");
  return `${day}.${month}.${date.getFullYear()} ${time}`;
}

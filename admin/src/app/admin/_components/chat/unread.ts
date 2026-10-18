"use client";

import { useEffect, useState } from "react";

/** Unread chat messages: the platform team's for a business, all businesses' for platform staff (null = not this side). */
export type SupportUnread = { business: number | null; platform: number | null };

const POLL_MS = 30_000;
const EVENT = "servio:support-activity";

/** A conversation was opened or a message sent: badges refresh now instead of on the next poll. */
export function notifySupportActivity() {
  window.dispatchEvent(new Event(EVENT));
}

export function useSupportUnread(enabled: boolean): SupportUnread {
  const [unread, setUnread] = useState<SupportUnread>({ business: null, platform: null });

  useEffect(() => {
    if (!enabled) return;
    let cancelled = false;

    async function load() {
      try {
        const response = await fetch("/api/admin/support/unread", { cache: "no-store" });
        if (!response.ok) return;
        const json = (await response.json()) as { data: SupportUnread | null };
        if (!cancelled && json.data) setUnread(json.data);
      } catch {
        // A missed poll just keeps the old badge.
      }
    }

    const first = setTimeout(load, 0);
    const timer = setInterval(() => {
      if (document.visibilityState === "visible") load();
    }, POLL_MS);
    window.addEventListener(EVENT, load);
    return () => {
      cancelled = true;
      clearTimeout(first);
      clearInterval(timer);
      window.removeEventListener(EVENT, load);
    };
  }, [enabled]);

  return unread;
}

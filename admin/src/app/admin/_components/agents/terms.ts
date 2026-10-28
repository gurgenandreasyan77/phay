import type { AgentTermDto, AgentTermStatus } from "@/lib/adminApi";
import type { BadgeTone } from "@/ui";

/** Each AI agent's own term, in words: its trial, paid months, ended, in the plan. */
export const TERM_LABELS: Record<AgentTermStatus, string> = {
  off: "Թիմում չէ",
  trial: "Փորձաշրջան",
  active: "Վճարված",
  expired: "Ավարտված",
  included: "Ներառված է փաթեթում",
};

export const TERM_TONES: Record<AgentTermStatus, BadgeTone> = {
  off: "default",
  trial: "info",
  active: "success",
  expired: "danger",
  included: "primary",
};

/** The colour of a term's ring: the agent's own while it runs, amber near the end, red after it. */
export function termTone(term: Pick<AgentTermDto, "status" | "ending">): "tint" | "warning" | "danger" | "muted" {
  if (term.status === "expired") return "danger";
  if (term.status === "off") return "muted";
  if (term.ending) return "warning";
  return "tint";
}

/** Whether money can move its end: it isn't inside the plan. */
export function payable(term: Pick<AgentTermDto, "status">): boolean {
  return term.status !== "included";
}

/** An ISO moment as a day in Yerevan for the date input: 2026-11-13. */
export function isoDay(iso: string | null): string {
  if (!iso) return "";
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Yerevan",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date(iso));
  return parts;
}

/** Today in Yerevan as 2026-11-02. */
export function todayDay(): string {
  return isoDay(new Date().toISOString());
}

/** 13.11 for a short label on the timeline. */
export function shortDay(iso: string | null): string {
  if (!iso) return "—";
  const parts = new Intl.DateTimeFormat("en-GB", { timeZone: "Asia/Yerevan", day: "2-digit", month: "2-digit" }).formatToParts(
    new Date(iso),
  );
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? "";
  return `${get("day")}.${get("month")}`;
}

import type { LeadStatus } from "@/lib/adminApi";
import type { BadgeTone } from "@/ui";

export const LEAD_STATUS_LABELS: Record<LeadStatus, string> = {
  new: "Նոր",
  contacted: "Կապ է հաստատվել",
  waiting_for_photos: "Սպասվում են լուսանկարներ",
  estimated: "Նախնական գնահատված",
  confirmed: "Հաստատված",
  in_progress: "Կատարման մեջ",
  completed: "Ավարտված",
  cancelled: "Չեղարկված",
};

export const LEAD_STATUS_TONES: Record<LeadStatus, BadgeTone> = {
  new: "info",
  contacted: "pending",
  waiting_for_photos: "warning",
  estimated: "warning",
  confirmed: "primary",
  in_progress: "primary",
  completed: "success",
  cancelled: "danger",
};

export const LEAD_STATUSES = Object.keys(LEAD_STATUS_LABELS) as LeadStatus[];

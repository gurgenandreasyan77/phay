import type { ContactChannelType } from "@/lib/api";

/** Short names of a business's contact channels on the platform level. */
export const CONTACT_LABELS: Record<ContactChannelType, string> = {
  phone: "Հեռ.",
  whatsapp: "WhatsApp",
  telegram: "Telegram",
  instagram: "Instagram",
  facebook: "Facebook",
  tiktok: "TikTok",
  viber: "Viber",
  email: "Email",
  other: "Այլ",
};

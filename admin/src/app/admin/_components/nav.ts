import type { LucideIcon } from "lucide-react";
import type { FeatureFlags } from "@/lib/api";
import {
  LayoutDashboard,
  ClipboardList,
  Users,
  Wrench,
  Car,
  Sofa,
  ShoppingBag,
  FolderTree,
  FileSearch,
  UserCog,
  ListChecks,
  CalendarDays,
  CreditCard,
  Receipt,
  FileText,
  Workflow,
  ShieldCheck,
  History,
  Settings,
  TrendingUp,
  Sparkles,
  Upload,
  Building2,
  Globe,
} from "lucide-react";

export type NavItem = {
  href: string;
  label: string;
  icon: LucideIcon;
  /** Not backed by the API yet — rendered as a disabled placeholder so the
   *  intended CRM structure is visible without faking a working screen. */
  soon?: boolean;
  /** Hidden entirely (not just disabled) while this feature flag is off. */
  requiresFeature?: keyof FeatureFlags;
};

export type NavGroup = {
  label: string;
  items: NavItem[];
};

/**
 * The admin of one business: what its owner always sees, and what a super
 * admin sees after entering a business. Modules appear only when that
 * business has them turned on.
 */
export const BUSINESS_NAV_GROUPS: NavGroup[] = [
  {
    label: "Հիմնական",
    items: [
      { href: "/admin", label: "Վահանակ", icon: LayoutDashboard },
      { href: "/admin/orders", label: "Պատվերներ", icon: ClipboardList },
      { href: "/admin/sales", label: "Վաճառքներ", icon: Upload, soon: true },
      { href: "/admin/analytics", label: "Վերլուծություն", icon: TrendingUp },
      { href: "/admin/assistant", label: "AI օգնական", icon: Sparkles },
      { href: "/admin/clients", label: "Հաճախորդներ", icon: Users, soon: true },
    ],
  },
  {
    label: "Կատալոգ",
    items: [{ href: "/admin/services", label: "Ծառայություններ", icon: Wrench }],
  },
  {
    label: "Մոդուլներ",
    items: [
      { href: "/admin/dry-cleaning", label: "Քիմմաքրում", icon: Sofa, requiresFeature: "dryCleaning" },
      { href: "/admin/auto-care", label: "Ավտոխնամք", icon: Car, requiresFeature: "autoCare" },
      { href: "/admin/products", label: "Խանութ", icon: ShoppingBag, requiresFeature: "market" },
      { href: "/admin/market/categories", label: "Կատեգորիաներ", icon: FolderTree, requiresFeature: "market" },
      { href: "/admin/market/tasks", label: "Խնդիրներ (SEO)", icon: FileSearch, requiresFeature: "market" },
    ],
  },
  {
    label: "Թիմ",
    items: [
      { href: "/admin/employees", label: "Աշխատակիցներ", icon: UserCog, soon: true },
      { href: "/admin/tasks", label: "Առաջադրանքներ", icon: ListChecks, soon: true },
      { href: "/admin/calendar", label: "Օրացույց", icon: CalendarDays, soon: true },
      { href: "/admin/roles", label: "Դերեր և իրավունքներ", icon: ShieldCheck, soon: true },
    ],
  },
  {
    label: "Ֆինանսներ",
    items: [
      { href: "/admin/payments", label: "Վճարումներ", icon: CreditCard, soon: true },
      { href: "/admin/expenses", label: "Ծախսեր", icon: Receipt, soon: true },
    ],
  },
  {
    label: "Այլ",
    items: [
      { href: "/admin/documents", label: "Փաստաթղթեր", icon: FileText, soon: true },
      { href: "/admin/automations", label: "Ավտոմատացումներ", icon: Workflow, soon: true },
      { href: "/admin/audit-log", label: "Գործողությունների մատյան", icon: History, soon: true },
      { href: "/admin/settings", label: "Կարգավորումներ", icon: Settings },
    ],
  },
];

/** The platform level: super admin only (the API answers 403 to an owner too). */
export const PLATFORM_NAV_GROUPS: NavGroup[] = [
  {
    label: "Պլատֆորմ",
    items: [
      { href: "/admin/platform", label: "Բիզնեսներ", icon: Building2 },
      { href: "/admin/platform/sites", label: "Կայքեր", icon: Globe },
    ],
  },
];

export const PLATFORM_HOME = "/admin/platform";

export function isPlatformPath(pathname: string): boolean {
  return pathname === PLATFORM_HOME || pathname.startsWith(`${PLATFORM_HOME}/`);
}

export const ALL_NAV_ITEMS: NavItem[] = [...BUSINESS_NAV_GROUPS, ...PLATFORM_NAV_GROUPS].flatMap((group) => group.items);

/**
 * The menu for this page: the platform level on /admin/platform/*, the
 * business admin elsewhere. Drops modules the business has off; a business
 * owner also doesn't see the "soon" placeholders, only what works today.
 */
export function visibleNavGroups(features: FeatureFlags, isSuperAdmin: boolean, pathname: string): NavGroup[] {
  if (isSuperAdmin && isPlatformPath(pathname)) return PLATFORM_NAV_GROUPS;

  return BUSINESS_NAV_GROUPS.map((group) => ({
    ...group,
    items: group.items.filter(
      (item) => (!item.requiresFeature || features[item.requiresFeature]) && (!item.soon || isSuperAdmin),
    ),
  })).filter((group) => group.items.length > 0);
}

/** Longest-prefix match so /admin/products/new still highlights "Խանութ". */
export function findActiveNavItem(pathname: string): NavItem | undefined {
  return [...ALL_NAV_ITEMS]
    .sort((a, b) => b.href.length - a.href.length)
    .find((item) => isActiveHref(item.href, pathname));
}

/** /admin and /admin/platform are section homes: only active on themselves. */
export function isActiveHref(href: string, pathname: string): boolean {
  return href === "/admin" || href === PLATFORM_HOME ? pathname === href : pathname.startsWith(href);
}

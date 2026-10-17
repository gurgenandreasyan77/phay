import type { LucideIcon } from "lucide-react";
import type { FeatureFlags } from "@/lib/api";
import {
  LayoutDashboard,
  ClipboardList,
  Users,
  Wrench,
  Car,
  ShoppingBag,
  FolderTree,
  FileSearch,
  UserCog,
  ListChecks,
  CalendarDays,
  CreditCard,
  Receipt,
  FileText,
  BarChart3,
  Workflow,
  ShieldCheck,
  History,
  Settings,
  ToggleLeft,
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
  /** Platform-level screens only a super admin can use. */
  superAdminOnly?: boolean;
};

export type NavGroup = {
  label: string;
  items: NavItem[];
};

export const NAV_GROUPS: NavGroup[] = [
  {
    label: "Հիմնական",
    items: [
      { href: "/admin", label: "Վահանակ", icon: LayoutDashboard },
      { href: "/admin/orders", label: "Պատվերներ", icon: ClipboardList },
      { href: "/admin/clients", label: "Հաճախորդներ", icon: Users, soon: true },
    ],
  },
  {
    label: "Կատալոգ",
    items: [
      { href: "/admin/services", label: "Ծառայություններ", icon: Wrench },
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
    label: "Պլատֆորմ",
    items: [
      { href: "/admin/businesses", label: "Բիզնեսներ", icon: Building2, superAdminOnly: true },
      { href: "/admin/sites", label: "Կայքեր", icon: Globe },
    ],
  },
  {
    label: "Այլ",
    items: [
      { href: "/admin/documents", label: "Փաստաթղթեր", icon: FileText, soon: true },
      { href: "/admin/analytics", label: "Վերլուծություն", icon: BarChart3, soon: true },
      { href: "/admin/automations", label: "Ավտոմատացումներ", icon: Workflow, soon: true },
      { href: "/admin/audit-log", label: "Գործողությունների մատյան", icon: History, soon: true },
      { href: "/admin/settings", label: "Կարգավորումներ", icon: Settings },
      // Deliberately never gated by requiresFeature — otherwise disabling a
      // flag could hide the only place that can turn it back on.
      { href: "/admin/feature-flags", label: "Feature Flags", icon: ToggleLeft },
    ],
  },
];

export const ALL_NAV_ITEMS: NavItem[] = NAV_GROUPS.flatMap((group) => group.items);

/** Drops nav items/groups gated behind a disabled feature flag or super-admin access. */
export function visibleNavGroups(features: FeatureFlags, isSuperAdmin = false): NavGroup[] {
  return NAV_GROUPS.map((group) => ({
    ...group,
    items: group.items.filter(
      (item) =>
        (!item.requiresFeature || features[item.requiresFeature]) && (!item.superAdminOnly || isSuperAdmin),
    ),
  })).filter((group) => group.items.length > 0);
}

/** Longest-prefix match so /admin/products/new still highlights "Խանութ". */
export function findActiveNavItem(pathname: string): NavItem | undefined {
  return [...ALL_NAV_ITEMS]
    .sort((a, b) => b.href.length - a.href.length)
    .find((item) => (item.href === "/admin" ? pathname === "/admin" : pathname.startsWith(item.href)));
}

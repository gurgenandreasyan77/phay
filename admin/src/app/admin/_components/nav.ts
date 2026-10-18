import type { LucideIcon } from "lucide-react";
import type { FeatureFlags } from "@/lib/api";
import type { AdminUserDto, BusinessPermission } from "@/lib/adminApi";
import { can, isPlatformStaff, isSuperAdmin } from "@/lib/roles";
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
  Sigma,
  Sparkles,
  Wallet,
  Building2,
  Globe,
  UsersRound,
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
  /** Hidden for business roles without this permission (see lib/roles). */
  requires?: BusinessPermission;
  /** Platform level: only the super admin. */
  superAdminOnly?: boolean;
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
      { href: "/admin", label: "Վահանակ", icon: LayoutDashboard, requires: "orders" },
      { href: "/admin/orders", label: "Պատվերներ", icon: ClipboardList, requiresFeature: "orders", requires: "orders" },
      { href: "/admin/calendar", label: "Օրացույց", icon: CalendarDays, requiresFeature: "orders", requires: "orders" },
      { href: "/admin/sales", label: "Վաճառքներ", icon: Receipt, requiresFeature: "sales", requires: "orders" },
      { href: "/admin/statistics", label: "Վիճակագրություն", icon: Sigma, requires: "insights" },
      { href: "/admin/analytics", label: "Վերլուծություն", icon: TrendingUp, requires: "insights" },
      { href: "/admin/assistant", label: "AI օգնական", icon: Sparkles, requires: "insights" },
      { href: "/admin/clients", label: "Հաճախորդներ", icon: Users, soon: true },
    ],
  },
  {
    label: "Կատալոգ",
    items: [{ href: "/admin/services", label: "Ծառայություններ", icon: Wrench, requires: "catalog" }],
  },
  {
    label: "Մոդուլներ",
    items: [
      { href: "/admin/dry-cleaning", label: "Քիմմաքրում", icon: Sofa, requiresFeature: "dryCleaning", requires: "catalog" },
      { href: "/admin/auto-care", label: "Ավտոխնամք", icon: Car, requiresFeature: "autoCare", requires: "catalog" },
      { href: "/admin/products", label: "Խանութ", icon: ShoppingBag, requiresFeature: "market", requires: "catalog" },
      { href: "/admin/market/categories", label: "Կատեգորիաներ", icon: FolderTree, requiresFeature: "market", requires: "catalog" },
      { href: "/admin/market/tasks", label: "Խնդիրներ (SEO)", icon: FileSearch, requiresFeature: "market", requires: "catalog" },
    ],
  },
  {
    label: "Թիմ",
    items: [
      { href: "/admin/employees", label: "Աշխատակիցներ", icon: UserCog, requires: "settings" },
      { href: "/admin/tasks", label: "Առաջադրանքներ", icon: ListChecks, soon: true },
      { href: "/admin/roles", label: "Դերեր և իրավունքներ", icon: ShieldCheck, soon: true },
    ],
  },
  {
    label: "Ֆինանսներ",
    items: [
      { href: "/admin/payments", label: "Վճարումներ", icon: CreditCard, soon: true },
      { href: "/admin/expenses", label: "Ծախսեր", icon: Wallet, soon: true },
    ],
  },
  {
    label: "Այլ",
    items: [
      { href: "/admin/documents", label: "Փաստաթղթեր", icon: FileText, soon: true },
      { href: "/admin/automations", label: "Ավտոմատացումներ", icon: Workflow, soon: true },
      { href: "/admin/audit-log", label: "Գործողությունների մատյան", icon: History, soon: true },
      { href: "/admin/settings", label: "Կարգավորումներ", icon: Settings, requires: "settings" },
    ],
  },
];

/** The platform level: platform staff only (the API answers 403 to a business's people too). */
export const PLATFORM_NAV_GROUPS: NavGroup[] = [
  {
    label: "Պլատֆորմ",
    items: [
      { href: "/admin/platform", label: "Բիզնեսներ", icon: Building2 },
      { href: "/admin/platform/sites", label: "Կայքեր", icon: Globe },
      { href: "/admin/platform/team", label: "Պլատֆորմի թիմ", icon: UsersRound, superAdminOnly: true },
    ],
  },
];

export const PLATFORM_HOME = "/admin/platform";

export function isPlatformPath(pathname: string): boolean {
  return pathname === PLATFORM_HOME || pathname.startsWith(`${PLATFORM_HOME}/`);
}

export const ALL_NAV_ITEMS: NavItem[] = [...BUSINESS_NAV_GROUPS, ...PLATFORM_NAV_GROUPS].flatMap((group) => group.items);

type NavUser = Pick<AdminUserDto, "platformRole" | "permissions"> | null;

/**
 * The menu for this page: the platform level on /admin/platform/*, the
 * business admin elsewhere. Drops modules the business has off and sections
 * the user's role doesn't open; a business's own people also don't see the
 * "soon" placeholders, only what works today.
 */
export function visibleNavGroups(features: FeatureFlags, me: NavUser, pathname: string): NavGroup[] {
  const staff = isPlatformStaff(me);

  const groups =
    staff && isPlatformPath(pathname)
      ? PLATFORM_NAV_GROUPS.map((group) => ({
          ...group,
          items: group.items.filter((item) => !item.superAdminOnly || isSuperAdmin(me)),
        }))
      : BUSINESS_NAV_GROUPS.map((group) => ({
          ...group,
          items: group.items.filter(
            (item) =>
              (!item.requiresFeature || features[item.requiresFeature]) &&
              (!item.requires || can(me, item.requires)) &&
              (!item.soon || staff),
          ),
        }));

  return groups.filter((group) => group.items.length > 0);
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

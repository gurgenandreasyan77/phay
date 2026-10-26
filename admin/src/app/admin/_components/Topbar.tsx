"use client";

import Link from "next/link";
import { useRouter, usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { ChevronDown, KeyRound, LogOut, Menu } from "lucide-react";
import type { FeatureFlags } from "@/lib/api";
import type { AdminUserDto } from "@/lib/adminApi";
import { findActiveNavItem, showsPlatformLevel } from "./nav";
import { TenantSwitcher } from "./TenantSwitcher";
import { HeaderSearch } from "./header/HeaderSearch";
import { NotificationBell } from "./header/NotificationBell";
import { PremiumChip } from "./header/PremiumChip";
import { cn } from "@/ui";

const EXTRA_SEGMENT_LABELS: Record<string, string> = {
  new: "Նոր",
  tables: "Աղյուսակներ",
  settings: "Կարգավորումներ",
  import: "Ներմուծում",
  site: "Կայքից",
  businesses: "Բիզնես",
};

function useBreadcrumbs(pathname: string) {
  const activeItem = findActiveNavItem(pathname);
  if (!activeItem) return [{ label: "Ադմին", href: "/admin" }];

  const crumbs = [{ label: activeItem.label, href: activeItem.href }];
  const rest = pathname.slice(activeItem.href.length).split("/").filter(Boolean);
  for (const [i, segment] of rest.entries()) {
    // A table's own id says nothing to the reader: "Աղյուսակ" already names it.
    if ((rest[i - 1] === "tables" || rest[i - 1] === "businesses") && /^\d+$/.test(segment)) continue;
    const label = EXTRA_SEGMENT_LABELS[segment] ?? (/^\d+$/.test(segment) ? "Խմբագրում" : segment);
    crumbs.push({ label, href: "" });
  }
  return crumbs;
}

export function Topbar({
  onOpenMobileNav,
  me,
  features,
}: {
  onOpenMobileNav: () => void;
  me: AdminUserDto | null;
  features: FeatureFlags;
}) {
  const adminEmail = me?.email;
  const pathname = usePathname();
  const router = useRouter();
  const crumbs = useBreadcrumbs(pathname);
  const [menuOpen, setMenuOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!menuOpen) return;
    function handleClick(e: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) setMenuOpen(false);
    }
    document.addEventListener("pointerdown", handleClick);
    return () => document.removeEventListener("pointerdown", handleClick);
  }, [menuOpen]);

  async function handleLogout() {
    setLoggingOut(true);
    await fetch("/api/admin/session", { method: "DELETE" });
    router.push("/admin/login");
    router.refresh();
  }

  return (
    <header
      className="sticky top-0 z-20 border-b border-border bg-surface"
      style={{
        paddingTop: "env(safe-area-inset-top, 0px)",
        paddingLeft: "env(safe-area-inset-left, 0px)",
        paddingRight: "env(safe-area-inset-right, 0px)",
      }}
    >
      <div className="flex h-14 items-center gap-1 pl-2 pr-2 sm:h-16 sm:gap-3 sm:px-6">
        <button
          type="button"
          onClick={onOpenMobileNav}
          className="flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-ink-muted hover:bg-surface-secondary active:bg-surface-secondary sm:-ml-1 sm:h-9 sm:w-9 lg:hidden"
          aria-label="Մենյու"
        >
          <Menu className="h-5 w-5" strokeWidth={1.75} />
        </button>

        {/* On a phone only the current page: the full path doesn't fit beside the buttons. */}
        <nav aria-label="Breadcrumb" className="flex min-w-0 flex-1 items-center gap-1.5 text-sm">
          {crumbs.map((crumb, i) => (
            <span
              key={`${crumb.label}-${i}`}
              className={cn("min-w-0 items-center gap-1.5", i === crumbs.length - 1 ? "flex" : "hidden shrink-0 md:flex")}
            >
              {i > 0 ? <span className="hidden text-ink-faint md:inline">/</span> : null}
              {crumb.href && i < crumbs.length - 1 ? (
                <Link href={crumb.href} className="truncate text-ink-muted hover:text-ink">
                  {crumb.label}
                </Link>
              ) : (
                <span className={cn("truncate", i === crumbs.length - 1 ? "font-medium text-ink" : "text-ink-muted")}>
                  {crumb.label}
                </span>
              )}
            </span>
          ))}
        </nav>

        <div className="ml-auto flex shrink-0 items-center sm:gap-1">
          {/* Only one's own businesses; on the platform level a business opens from its row. */}
          {me?.currentTenant && !showsPlatformLevel(me, pathname) ? (
            <TenantSwitcher current={me.currentTenant} tenants={me.tenants} />
          ) : null}
          {/* An invitation for a business not on premium; not on the platform level. */}
          {me?.subscription && me.subscription.premium === false && !showsPlatformLevel(me, pathname) ? <PremiumChip /> : null}
          <HeaderSearch features={features} me={me} />
          <NotificationBell enabled={me !== null} />

          <div className="relative ml-1" ref={menuRef}>
            <button
              type="button"
              onClick={() => setMenuOpen((v) => !v)}
              aria-label="Հաշիվ"
              className="flex h-11 items-center gap-2 rounded-md px-2 hover:bg-surface-secondary active:bg-surface-secondary sm:h-auto sm:py-1.5 sm:pl-1.5"
            >
              <span className="flex h-7 w-7 items-center justify-center rounded-full bg-primary-light text-xs font-semibold text-primary">
                {(adminEmail ?? "A").slice(0, 1).toUpperCase()}
              </span>
              <span className="hidden text-sm text-ink sm:inline">{adminEmail ?? "Ադմինիստրատոր"}</span>
              <ChevronDown className="hidden h-3.5 w-3.5 text-ink-muted sm:block" strokeWidth={1.75} />
            </button>

            {menuOpen ? (
              <div className="absolute right-0 top-full mt-1.5 w-64 max-w-[calc(100vw-1rem)] rounded-lg border border-border bg-surface p-1.5 shadow-md">
                <div className="px-2.5 py-2">
                  <p className="truncate text-sm font-medium text-ink">{adminEmail ?? "Ադմինիստրատոր"}</p>
                  <p className="text-xs text-ink-muted">{me?.currentTenant?.name ?? "Phay"}</p>
                </div>
                <div className="my-1 h-px bg-border" />
                <Link
                  href="/admin/account"
                  onClick={() => setMenuOpen(false)}
                  className="flex min-h-11 w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm text-ink hover:bg-surface-secondary active:bg-surface-secondary sm:min-h-0"
                >
                  <KeyRound className="h-4 w-4" strokeWidth={1.75} />
                  Իմ հաշիվը և գաղտնաբառը
                </Link>
                <button
                  type="button"
                  onClick={handleLogout}
                  disabled={loggingOut}
                  className="flex min-h-11 w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm text-ink hover:bg-surface-secondary active:bg-surface-secondary disabled:opacity-50 sm:min-h-0"
                >
                  <LogOut className="h-4 w-4" strokeWidth={1.75} />
                  Ելք
                </button>
              </div>
            ) : null}
          </div>
        </div>
      </div>
    </header>
  );
}

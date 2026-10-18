"use client";

import Link from "next/link";
import { useRouter, usePathname } from "next/navigation";
import { isPlatformStaff } from "@/lib/roles";
import { useEffect, useRef, useState } from "react";
import { Bell, ChevronDown, LogOut, Menu, Search } from "lucide-react";
import type { AdminUserDto } from "@/lib/adminApi";
import { findActiveNavItem, isPlatformPath } from "./nav";
import { TenantSwitcher } from "./TenantSwitcher";
import { cn } from "./ui";

const EXTRA_SEGMENT_LABELS: Record<string, string> = {
  new: "Նոր",
  tables: "Աղյուսակներ",
  settings: "Կարգավորումներ",
  import: "Ներմուծում",
  site: "Կայքից",
};

function useBreadcrumbs(pathname: string) {
  const activeItem = findActiveNavItem(pathname);
  if (!activeItem) return [{ label: "Ադմին", href: "/admin" }];

  const crumbs = [{ label: activeItem.label, href: activeItem.href }];
  const rest = pathname.slice(activeItem.href.length).split("/").filter(Boolean);
  for (const [i, segment] of rest.entries()) {
    // A table's own id says nothing to the reader: "Աղյուսակ" already names it.
    if (rest[i - 1] === "tables" && /^\d+$/.test(segment)) continue;
    const label = EXTRA_SEGMENT_LABELS[segment] ?? (/^\d+$/.test(segment) ? "Խմբագրում" : segment);
    crumbs.push({ label, href: "" });
  }
  return crumbs;
}

export function Topbar({ onOpenMobileNav, me }: { onOpenMobileNav: () => void; me: AdminUserDto | null }) {
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
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, [menuOpen]);

  async function handleLogout() {
    setLoggingOut(true);
    await fetch("/api/admin/session", { method: "DELETE" });
    router.push("/admin/login");
    router.refresh();
  }

  return (
    <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-border bg-surface px-4 sm:px-6">
      <button
        type="button"
        onClick={onOpenMobileNav}
        className="-ml-1 flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-surface-secondary lg:hidden"
        aria-label="Մենյու"
      >
        <Menu className="h-5 w-5" strokeWidth={1.75} />
      </button>

      <nav aria-label="Breadcrumb" className="flex min-w-0 items-center gap-1.5 text-sm">
        {crumbs.map((crumb, i) => (
          <span key={`${crumb.label}-${i}`} className="flex items-center gap-1.5">
            {i > 0 ? <span className="text-ink-faint">/</span> : null}
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

      <div className="ml-auto flex items-center gap-1">
        {/* On the platform level a business is entered from its row instead. */}
        {me?.currentTenant && !(isPlatformStaff(me) && isPlatformPath(pathname)) ? (
          <TenantSwitcher current={me.currentTenant} tenants={me.tenants} />
        ) : null}
        <button
          type="button"
          disabled
          title="Շուտով"
          className="flex h-9 w-9 items-center justify-center rounded-md text-ink-faint disabled:cursor-not-allowed"
        >
          <Search className="h-4.5 w-4.5" strokeWidth={1.75} />
        </button>
        <button
          type="button"
          disabled
          title="Շուտով"
          className="flex h-9 w-9 items-center justify-center rounded-md text-ink-faint disabled:cursor-not-allowed"
        >
          <Bell className="h-4.5 w-4.5" strokeWidth={1.75} />
        </button>

        <div className="relative ml-1" ref={menuRef}>
          <button
            type="button"
            onClick={() => setMenuOpen((v) => !v)}
            className="flex items-center gap-2 rounded-md py-1.5 pl-1.5 pr-2 hover:bg-surface-secondary"
          >
            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-primary-light text-xs font-semibold text-primary">
              {(adminEmail ?? "A").slice(0, 1).toUpperCase()}
            </span>
            <span className="hidden text-sm text-ink sm:inline">{adminEmail ?? "Ադմինիստրատոր"}</span>
            <ChevronDown className="h-3.5 w-3.5 text-ink-muted" strokeWidth={1.75} />
          </button>

          {menuOpen ? (
            <div className="absolute right-0 top-full mt-1.5 w-56 rounded-lg border border-border bg-surface p-1.5 shadow-md">
              <div className="px-2.5 py-2">
                <p className="text-sm font-medium text-ink">{adminEmail ?? "Ադմինիստրատոր"}</p>
                <p className="text-xs text-ink-muted">{me?.currentTenant?.name ?? "Servio"}</p>
              </div>
              <div className="my-1 h-px bg-border" />
              <button
                type="button"
                onClick={handleLogout}
                disabled={loggingOut}
                className="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm text-ink hover:bg-surface-secondary disabled:opacity-50"
              >
                <LogOut className="h-4 w-4" strokeWidth={1.75} />
                Ելք
              </button>
            </div>
          ) : null}
        </div>
      </div>
    </header>
  );
}

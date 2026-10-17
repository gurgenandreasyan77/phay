"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { FeatureFlags } from "@/lib/api";
import { visibleNavGroups } from "./nav";
import { cn } from "./ui";

export function Sidebar({
  features,
  isSuperAdmin = false,
  onNavigate,
  className,
}: {
  features: FeatureFlags;
  isSuperAdmin?: boolean;
  onNavigate?: () => void;
  className?: string;
}) {
  const pathname = usePathname();
  const navGroups = visibleNavGroups(features, isSuperAdmin);

  return (
    <nav className={cn("flex h-full flex-col bg-sidebar text-sidebar-text", className)}>
      <div className="flex h-16 items-center gap-2.5 px-5">
        <span className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">
          S
        </span>
        <div className="leading-tight">
          <p className="text-sm font-semibold text-sidebar-text">Servio</p>
          <p className="text-xs text-sidebar-text-muted">Ադմին վահանակ</p>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto px-3 pb-4">
        {navGroups.map((group) => (
          <div key={group.label} className="mb-5">
            <p className="px-3 pb-1.5 text-[11px] font-medium uppercase tracking-wide text-sidebar-text-muted">
              {group.label}
            </p>
            <ul className="space-y-0.5">
              {group.items.map((item) => {
                const isActive = item.href === "/admin" ? pathname === "/admin" : pathname.startsWith(item.href);
                const Icon = item.icon;

                if (item.soon) {
                  return (
                    <li key={item.href}>
                      <span className="flex cursor-default items-center gap-2.5 rounded-md px-3 py-2 text-sm text-sidebar-text-muted/70">
                        <Icon className="h-4 w-4 shrink-0" strokeWidth={1.75} />
                        <span className="flex-1">{item.label}</span>
                        <span className="rounded bg-sidebar-hover px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-sidebar-text-muted">
                          Շուտով
                        </span>
                      </span>
                    </li>
                  );
                }

                return (
                  <li key={item.href} className="relative">
                    {isActive ? (
                      <span className="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-primary" />
                    ) : null}
                    <Link
                      href={item.href}
                      onClick={onNavigate}
                      className={cn(
                        "flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-colors",
                        isActive
                          ? "bg-sidebar-hover font-medium text-sidebar-text"
                          : "text-sidebar-text-muted hover:bg-sidebar-hover hover:text-sidebar-text",
                      )}
                    >
                      <Icon className="h-4 w-4 shrink-0" strokeWidth={1.75} />
                      {item.label}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </div>
    </nav>
  );
}

"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { FeatureFlags } from "@/lib/api";
import type { AdminUserDto } from "@/lib/adminApi";
import { isPlatformStaff } from "@/lib/roles";
import { ArrowLeft } from "lucide-react";
import { PLATFORM_HOME, isActiveHref, isPlatformPath, visibleNavGroups } from "./nav";
import { cn } from "@/ui";
import { useSupportUnread } from "./chat/unread";
import { BusinessLogo } from "./BusinessLogo";

export function Sidebar({
  features,
  me,
  businessName,
  businessLogo,
  onNavigate,
  className,
}: {
  features: FeatureFlags;
  me: AdminUserDto | null;
  /** The business this admin works on (shown in business mode). */
  businessName?: string;
  /** The business's logo, if it uploaded one in its settings. */
  businessLogo?: string | null;
  onNavigate?: () => void;
  className?: string;
}) {
  const pathname = usePathname();
  const staff = isPlatformStaff(me);
  const navGroups = visibleNavGroups(features, me, pathname);
  const onPlatform = staff && isPlatformPath(pathname);
  const unread = useSupportUnread(me !== null);

  return (
    <nav className={cn("flex h-full flex-col bg-sidebar text-sidebar-text", className)}>
      <div className="flex h-16 items-center gap-2.5 px-5">
        {!onPlatform && businessLogo ? (
          <BusinessLogo name={businessName ?? "Phay"} logo={businessLogo} size="sm" className="border-0" />
        ) : (
          <span className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">
            S
          </span>
        )}
        <div className="min-w-0 leading-tight">
          <p className="truncate text-sm font-semibold text-sidebar-text">
            {onPlatform ? "Phay" : (businessName ?? "Phay")}
          </p>
          <p className="text-xs text-sidebar-text-muted">{onPlatform ? "Պլատֆորմ" : "Ադմին վահանակ"}</p>
        </div>
      </div>

      {staff && !onPlatform ? (
        <div className="px-3 pb-3">
          <Link
            href={PLATFORM_HOME}
            onClick={onNavigate}
            className="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-sidebar-text-muted hover:bg-sidebar-hover hover:text-sidebar-text"
          >
            <ArrowLeft className="h-4 w-4 shrink-0" strokeWidth={1.75} />
            Պլատֆորմ
          </Link>
        </div>
      ) : null}

      <div className="flex-1 overflow-y-auto px-3 pb-4">
        {navGroups.map((group) => (
          <div key={group.label} className="mb-5">
            <p className="px-3 pb-1.5 text-[11px] font-medium uppercase tracking-wide text-sidebar-text-muted">
              {group.label}
            </p>
            <ul className="space-y-0.5">
              {group.items.map((item) => {
                const isActive = isActiveHref(item.href, pathname);
                const Icon = item.icon;
                const count = item.badge ? (unread[item.badge] ?? 0) : 0;

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
                      <span className="flex-1">{item.label}</span>
                      {count > 0 ? (
                        <span className="min-w-5 rounded-full bg-primary px-1.5 py-0.5 text-center text-[11px] font-semibold leading-none text-primary-foreground">
                          {count > 99 ? "99+" : count}
                        </span>
                      ) : null}
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

"use client";

import { usePathname } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import { X } from "lucide-react";
import type { FeatureFlags } from "@/lib/api";
import type { AdminUserDto } from "@/lib/adminApi";
import { isPlatformStaff } from "@/lib/roles";
import { showsPlatformLevel } from "./nav";
import { Sidebar } from "./Sidebar";
import { SubscriptionBanner } from "./subscription/SubscriptionBanner";
import { Topbar } from "./Topbar";

/** Sign-in pages and the 2FA setup that comes before everything: no menu. */
const BARE_PATHS = [
  "/admin/login",
  "/admin/forgot-password",
  "/admin/reset-password",
  "/admin/invite",
  "/admin/setup-2fa",
  "/admin/maintenance",
];

export function AdminShell({
  features,
  me,
  children,
}: {
  features: FeatureFlags;
  me: AdminUserDto | null;
  children: ReactNode;
}) {
  const pathname = usePathname();
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [lastPathname, setLastPathname] = useState(pathname);

  if (pathname !== lastPathname) {
    setLastPathname(pathname);
    setMobileNavOpen(false);
  }

  // The open menu holds the page still behind it and closes with Escape.
  useEffect(() => {
    if (!mobileNavOpen) return;
    const { overflow } = document.body.style;
    document.body.style.overflow = "hidden";
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setMobileNavOpen(false);
    document.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = overflow;
      document.removeEventListener("keydown", onKey);
    };
  }, [mobileNavOpen]);

  if (BARE_PATHS.includes(pathname)) {
    return <div className="min-h-screen bg-background">{children}</div>;
  }

  return (
    <div className="min-h-screen bg-background lg:pl-64">
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 lg:block">
        <Sidebar
          features={features}
          me={me}
          businessName={me?.currentTenant?.name}
          businessLogo={me?.currentTenant?.logo}
        />
      </aside>

      {mobileNavOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden">
          <button
            type="button"
            aria-label="Փակել մենյուն"
            className="absolute inset-0 bg-ink/40"
            onClick={() => setMobileNavOpen(false)}
          />
          <aside className="absolute inset-y-0 left-0 w-72 max-w-[85vw] bg-sidebar shadow-md">
            <button
              type="button"
              aria-label="Փակել մենյուն"
              onClick={() => setMobileNavOpen(false)}
              className="absolute right-2 z-10 flex h-11 w-11 items-center justify-center rounded-md text-sidebar-text-muted active:bg-sidebar-hover"
              style={{ top: "calc(0.5rem + env(safe-area-inset-top, 0px))" }}
            >
              <X className="h-5 w-5" strokeWidth={1.75} />
            </button>
            <Sidebar
              mobile
              features={features}
              me={me}
              businessName={me?.currentTenant?.name}
              businessLogo={me?.currentTenant?.logo}
              onNavigate={() => setMobileNavOpen(false)}
            />
          </aside>
        </div>
      ) : null}

      <Topbar onOpenMobileNav={() => setMobileNavOpen(true)} me={me} features={features} />
      {me?.subscription && (me.subscription.ending || me.subscription.locked) && !isPlatformStaff(me) && !showsPlatformLevel(me, pathname) ? (
        <SubscriptionBanner subscription={me.subscription} />
      ) : null}
      <main
        className="min-w-0 py-5 sm:py-6"
        style={{
          // Clear of the rounded corners and the home indicator in landscape.
          paddingLeft: "max(1rem, env(safe-area-inset-left, 0px))",
          paddingRight: "max(1rem, env(safe-area-inset-right, 0px))",
          paddingBottom: "calc(1.5rem + env(safe-area-inset-bottom, 0px))",
        }}
      >
        <div className="sm:px-2 lg:px-4">{children}</div>
      </main>
    </div>
  );
}

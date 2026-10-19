"use client";

import { usePathname } from "next/navigation";
import { useState, type ReactNode } from "react";
import type { FeatureFlags } from "@/lib/api";
import type { AdminUserDto } from "@/lib/adminApi";
import { Sidebar } from "./Sidebar";
import { Topbar } from "./Topbar";

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

  if (pathname === "/admin/login") {
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
          <aside className="absolute inset-y-0 left-0 w-64 shadow-md">
            <Sidebar
              features={features}
              me={me}
              businessName={me?.currentTenant?.name}
              businessLogo={me?.currentTenant?.logo}
              onNavigate={() => setMobileNavOpen(false)}
            />
          </aside>
        </div>
      ) : null}

      <Topbar onOpenMobileNav={() => setMobileNavOpen(true)} me={me} />
      <main className="px-4 py-6 sm:px-6 lg:px-8">{children}</main>
    </div>
  );
}

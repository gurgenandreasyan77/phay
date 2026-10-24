import type { Metadata } from "next";
import "./admin.css";
import { getFeatureFlagsAdmin, getMe } from "@/lib/adminApi";
import { AdminShell } from "./_components/AdminShell";

// The admin is its own Next.js app, so this is the root layout and owns
// <html>/<body>.
export const metadata: Metadata = {
  title: "Phay ադմին",
  robots: { index: false, follow: false },
};

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  // Unauthenticated (e.g. on /admin/login) this just comes back empty —
  // flags default to visible so a fetch hiccup doesn't hide the sidebar.
  const [flags, me] = await Promise.all([getFeatureFlagsAdmin(), getMe()]);
  const isActive = (key: string) => flags.find((f) => f.key === key)?.active ?? true;
  const features = {
    services: isActive("services"),
    market: isActive("market"),
    site: isActive("site"),
  };

  return (
    <html lang="hy" suppressHydrationWarning>
      {/* suppressHydrationWarning: browser extensions (e.g. ColorZilla's
          cz-shortcut-listen) inject attributes into <body> before React
          hydrates. */}
      <body className="min-h-screen bg-background text-ink antialiased" suppressHydrationWarning>
        <AdminShell features={features} me={me}>
          {children}
        </AdminShell>
      </body>
    </html>
  );
}

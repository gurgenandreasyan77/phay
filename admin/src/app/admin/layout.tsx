import type { Metadata, Viewport } from "next";
import "./admin.css";
import { getFeatureFlagsAdmin, getMe } from "@/lib/adminApi";
import { AdminShell } from "./_components/AdminShell";
import { PwaSetup } from "./_components/pwa/PwaSetup";

// The admin is its own Next.js app, so this is the root layout and owns
// <html>/<body>.
export const metadata: Metadata = {
  title: "Phay ադմին",
  applicationName: "Phay",
  robots: { index: false, follow: false },
  // Added to the iPhone home screen it opens full screen, named Phay.
  appleWebApp: { capable: true, title: "Phay", statusBarStyle: "default" },
  // The home-screen icon on iPhone and iPad: the colour Lagoon mark on white (iOS rounds it itself).
  // New file names, not just ?v=, because Safari keeps touch icons per URL past clearing its data;
  // app/icon.svg|png and favicon.ico are the same mark, so whichever icon iOS picks is the logo.
  icons: {
    apple: [
      { url: "/icons/phay-apple-180.png?v=4", sizes: "180x180", type: "image/png" },
      { url: "/icons/phay-apple-167.png?v=4", sizes: "167x167", type: "image/png" },
      { url: "/icons/phay-apple-152.png?v=4", sizes: "152x152", type: "image/png" },
    ],
  },
  formatDetection: { telephone: false },
};

// viewport-fit=cover lets the header and sheets reach under the notch and
// pad themselves with env(safe-area-inset-*). No maximum-scale: fields are
// 16px on touch screens instead (admin.css), so pinch zoom stays.
export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
  themeColor: "#ffffff",
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
    social: isActive("social"),
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
        <PwaSetup signedIn={me !== null} />
      </body>
    </html>
  );
}

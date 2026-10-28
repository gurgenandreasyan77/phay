import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
  async redirects() {
    // Every screen lives under /admin (kept from when the admin was part of
    // the public site), so the bare domain just forwards to the dashboard.
    return [{ source: "/", destination: "/admin", permanent: false }];
  },
  async headers() {
    // The service worker is checked for a new version on every visit.
    return [
      {
        source: "/sw.js",
        headers: [
          { key: "Cache-Control", value: "no-cache, no-store, must-revalidate" },
          { key: "Content-Type", value: "application/javascript; charset=utf-8" },
        ],
      },
    ];
  },
};

export default nextConfig;

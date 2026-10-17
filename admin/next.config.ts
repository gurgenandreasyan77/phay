import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
  async redirects() {
    // Every screen lives under /admin (kept from when the admin was part of
    // the public site), so the bare domain just forwards to the dashboard.
    return [{ source: "/", destination: "/admin", permanent: false }];
  },
};

export default nextConfig;

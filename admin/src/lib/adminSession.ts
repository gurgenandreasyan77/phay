// No server-only imports here (e.g. `next/headers`): shared with
// src/proxy.ts, which must stay lean, and with client components.
export const ADMIN_TOKEN_COOKIE = "admin_token";

/** The path (and query) of the page being opened, set by src/proxy.ts for server components. */
export const PATH_HEADER = "x-phay-path";

/**
 * Where to go back to after signing in: only a page of the admin itself
 * (never another site, never the sign-in page again).
 */
export function safeNextPath(next: string | null | undefined): string | null {
  if (!next || !next.startsWith("/admin") || next.startsWith("//") || next.includes("\\")) return null;
  const path = next.split(/[?#]/)[0];
  if (path === "/admin/login" || path.startsWith("/admin/login/")) return null;
  return next;
}

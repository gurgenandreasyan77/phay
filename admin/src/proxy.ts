import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { ADMIN_TOKEN_COOKIE, PATH_HEADER } from "./lib/adminSession";

// Fast-path redirect for UX only — every admin Server Component/Route
// Handler independently re-verifies the session server-side (see
// src/lib/adminAuth.ts).
const PUBLIC_PATHS = [
  "/admin/login",
  "/admin/forgot-password",
  "/admin/reset-password",
  "/admin/invite",
  "/admin/maintenance",
];

export function proxy(request: NextRequest) {
  const { pathname, search } = request.nextUrl;

  // Every screen lives under /admin: an address typed without it
  // (/orders, /login) goes to the same screen under /admin. One that
  // doesn't exist there either ends on the admin's 404 page.
  if (pathname !== "/admin" && !pathname.startsWith("/admin/")) {
    const url = request.nextUrl.clone();
    url.pathname = pathname === "/" ? "/admin" : `/admin${pathname.replace(/\/+$/, "")}`;
    return NextResponse.redirect(url);
  }

  // Sign-in and the pages opened from an email link work without a session.
  if (PUBLIC_PATHS.includes(pathname)) {
    return NextResponse.next();
  }

  // Signed out: to sign-in, and back to this page afterwards.
  if (!request.cookies.has(ADMIN_TOKEN_COOKIE)) {
    const url = request.nextUrl.clone();
    url.pathname = "/admin/login";
    url.search = pathname === "/admin" ? "" : `?next=${encodeURIComponent(pathname + search)}`;
    return NextResponse.redirect(url);
  }

  const headers = new Headers(request.headers);
  headers.set(PATH_HEADER, pathname + search);
  return NextResponse.next({ request: { headers } });
}

export const config = {
  // Everything but Next's own files, the route handlers and the icons.
  matcher: ["/((?!_next/|api/|favicon\\.ico|icon\\.png|robots\\.txt|sitemap\\.xml).*)"],
};

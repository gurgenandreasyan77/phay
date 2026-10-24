import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { ADMIN_TOKEN_COOKIE } from "./lib/adminSession";

// Fast-path redirect for UX only — every admin Server Component/Route
// Handler independently re-verifies the session server-side (see
// src/lib/adminAuth.ts).
const PUBLIC_PATHS = ["/admin/login", "/admin/forgot-password", "/admin/reset-password", "/admin/invite"];

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // Sign-in and the pages opened from an email link work without a session.
  if (PUBLIC_PATHS.includes(pathname)) {
    return NextResponse.next();
  }

  if (!request.cookies.has(ADMIN_TOKEN_COOKIE)) {
    return NextResponse.redirect(new URL("/admin/login", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/admin/:path*"],
};

import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { ADMIN_TOKEN_COOKIE } from "./lib/adminSession";

// Fast-path redirect for UX only — every admin Server Component/Route
// Handler independently re-verifies the session server-side (see
// src/lib/adminAuth.ts).
export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (pathname === "/admin/login") {
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

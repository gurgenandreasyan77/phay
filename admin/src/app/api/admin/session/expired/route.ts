import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { ADMIN_TOKEN_COOKIE, safeNextPath } from "@/lib/adminSession";
import { requestOrigin } from "@/lib/requestOrigin";

/**
 * The API no longer knows the session's token (expired, revoked, signed
 * out elsewhere): the cookie is dropped, so src/proxy.ts sends the next
 * pages straight to sign-in, and the person signs in again and comes back
 * to the page they opened.
 */
export async function GET(request: NextRequest) {
  const next = safeNextPath(request.nextUrl.searchParams.get("next"));
  const url = new URL("/admin/login", requestOrigin(request));
  if (next && next !== "/admin") url.searchParams.set("next", next);
  url.searchParams.set("expired", "1");

  const response = NextResponse.redirect(url);
  response.cookies.delete(ADMIN_TOKEN_COOKIE);
  return response;
}

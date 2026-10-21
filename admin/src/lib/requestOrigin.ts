import type { NextRequest } from "next/server";

/**
 * The address the browser used to reach the admin (behind Docker or a proxy
 * the request's own URL may name the container), for redirects back to it.
 */
export function requestOrigin(request: NextRequest): string {
  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host");
  const proto = request.headers.get("x-forwarded-proto") ?? request.nextUrl.protocol.replace(":", "");
  return host ? `${proto}://${host}` : request.nextUrl.origin;
}

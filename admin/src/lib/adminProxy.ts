// Server-only: route handlers that pass a call through to the Laravel API
// as is (body and status), for the sign-in and account-security screens.
import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { cookies } from "next/headers";
import { ADMIN_TOKEN_COOKIE } from "./adminSession";

const API_BASE =
  process.env.NEXT_INTERNAL_API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  "http://localhost:8080/api/v1";

/** The admin's session lasts as long as the API token (sanctum.expiration). */
const SESSION_MAX_AGE = 60 * 60 * 24 * 7;

/**
 * Who is really asking: the API limits sign-in attempts per IP and logs the
 * IP and browser of every security event, so it must not see only this
 * server (it trusts X-Forwarded-For from private networks only).
 */
function clientHeaders(request: NextRequest): Record<string, string> {
  const headers: Record<string, string> = {};
  const forwarded = request.headers.get("x-forwarded-for");
  const agent = request.headers.get("user-agent");
  if (forwarded) headers["X-Forwarded-For"] = forwarded;
  if (agent) headers["User-Agent"] = agent;
  return headers;
}

export async function callApi(
  request: NextRequest,
  path: string,
  init: { method?: string; body?: string; auth?: boolean } = {},
): Promise<{ status: number; json: unknown }> {
  const token = init.auth === false ? undefined : (await cookies()).get(ADMIN_TOKEN_COOKIE)?.value;

  const response = await fetch(`${API_BASE}${path}`, {
    method: init.method ?? request.method,
    body: init.body,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...clientHeaders(request),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    cache: "no-store",
  });

  return { status: response.status, json: response.status === 204 ? null : await response.json().catch(() => null) };
}

/** Passes the request's method and body to `path` and the answer back unchanged. */
export async function proxy(request: NextRequest, path: string, options: { auth?: boolean } = {}): Promise<NextResponse> {
  const body = ["GET", "HEAD"].includes(request.method) ? undefined : await request.text();
  const result = await callApi(request, path, { body, auth: options.auth });

  return result.status === 204 ? new NextResponse(null, { status: 204 }) : NextResponse.json(result.json, { status: result.status });
}

/** Keeps the API token in the httpOnly cookie; the browser never sees it. */
export async function setSessionCookie(token: string): Promise<void> {
  const store = await cookies();
  store.set(ADMIN_TOKEN_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: SESSION_MAX_AGE,
  });
}

type SignedIn = { data?: { token?: string; user?: Record<string, unknown> } };

/**
 * An API answer that signs in (login, 2FA step, accepted invitation): the
 * token goes to the cookie, the rest to the browser.
 */
export async function signInFrom(result: { status: number; json: unknown }): Promise<NextResponse> {
  const json = result.json as SignedIn | null;

  if (result.status !== 200) {
    return NextResponse.json(result.json, { status: result.status });
  }

  const token = json?.data?.token;
  if (!token) {
    return NextResponse.json({ message: "Unexpected response from API" }, { status: 502 });
  }

  await setSessionCookie(token);
  return NextResponse.json({ data: { user: json.data?.user } });
}

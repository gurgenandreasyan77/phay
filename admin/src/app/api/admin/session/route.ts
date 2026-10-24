import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { cookies } from "next/headers";
import { ADMIN_TOKEN_COOKIE } from "@/lib/adminSession";
import { callApi, signInFrom } from "@/lib/adminProxy";

/**
 * Sign-in, step one. Without 2FA the API gives a token (kept in the
 * cookie); with 2FA a challenge for /api/admin/session/two-factor instead.
 */
export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await callApi(request, "/admin/login", { method: "POST", body, auth: false });
  const json = result.json as { data?: { twoFactor?: boolean; challenge?: string; expiresIn?: number } } | null;

  if (result.status === 200 && json?.data?.twoFactor) {
    return NextResponse.json({ data: { twoFactor: true, challenge: json.data.challenge, expiresIn: json.data.expiresIn } });
  }

  return signInFrom(result);
}

export async function DELETE(request: NextRequest) {
  const store = await cookies();

  if (store.get(ADMIN_TOKEN_COOKIE)?.value) {
    await callApi(request, "/admin/logout", { method: "POST" }).catch(() => null);
  }

  store.delete(ADMIN_TOKEN_COOKIE);
  return NextResponse.json({ data: { status: "ok" } });
}

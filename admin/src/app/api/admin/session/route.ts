import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { cookies } from "next/headers";
import { ADMIN_TOKEN_COOKIE } from "@/lib/adminSession";

const API_BASE =
  process.env.NEXT_INTERNAL_API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  "http://localhost:8080/api/v1";

export async function POST(request: NextRequest) {
  const body = await request.json().catch(() => null);

  const response = await fetch(`${API_BASE}/admin/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });

  const json = await response.json().catch(() => null);

  if (!response.ok) {
    return NextResponse.json(
      { error: json?.message ?? "Սխալ էլ. հասցե կամ գաղտնաբառ" },
      { status: response.status },
    );
  }

  const token = json?.data?.token as string | undefined;
  if (!token) {
    return NextResponse.json({ error: "Unexpected response from API" }, { status: 502 });
  }

  const store = await cookies();
  store.set(ADMIN_TOKEN_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: 60 * 60 * 24 * 7,
  });

  return NextResponse.json({ data: { user: json.data.user } });
}

export async function DELETE() {
  const store = await cookies();
  const token = store.get(ADMIN_TOKEN_COOKIE)?.value;

  if (token) {
    await fetch(`${API_BASE}/admin/logout`, {
      method: "POST",
      headers: { Authorization: `Bearer ${token}` },
    }).catch(() => null);
  }

  store.delete(ADMIN_TOKEN_COOKIE);
  return NextResponse.json({ data: { status: "ok" } });
}

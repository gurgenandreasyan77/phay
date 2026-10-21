import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/**
 * Google comes back here (GOOGLE_REDIRECT_URI) with a code, or with an error
 * when the person declined. The API checks the state is theirs and keeps
 * the tokens; the person lands on the calendar to choose calendars.
 */
export async function GET(request: NextRequest) {
  const params = request.nextUrl.searchParams;
  const code = params.get("code");
  const state = params.get("state");
  const back = (result: string) => NextResponse.redirect(new URL(`/admin/calendar?google=${result}`, request.nextUrl.origin));

  if (params.get("error") || !code || !state) {
    return back(params.get("error") === "access_denied" ? "denied" : "error");
  }

  const result = await adminApiFetchRaw("/admin/google/callback", {
    method: "POST",
    body: JSON.stringify({ code, state }),
  });

  if (result.status === 200) return back("connected");
  if (result.status === 401) return NextResponse.redirect(new URL("/admin/login", request.nextUrl.origin));
  return back((result.json as { code?: string } | null)?.code === "google_state" ? "expired" : "error");
}

import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";
import { requestOrigin } from "@/lib/requestOrigin";

/**
 * Meta comes back here (META_REDIRECT_URI) with a code, or with an error
 * when the person cancelled. The API checks the state is theirs, keeps the
 * token and reads the Pages; the person lands on the social screen.
 */
export async function GET(request: NextRequest) {
  const params = request.nextUrl.searchParams;
  const code = params.get("code");
  const state = params.get("state");
  const back = (result: string) => NextResponse.redirect(new URL(`/admin/social?meta=${result}`, requestOrigin(request)));

  if (params.get("error") || !code || !state) {
    return back(params.get("error") === "access_denied" ? "denied" : "error");
  }

  const result = await adminApiFetchRaw("/admin/social/callback", {
    method: "POST",
    body: JSON.stringify({ code, state }),
  });

  if (result.status === 200) return back("connected");
  if (result.status === 401) return NextResponse.redirect(new URL("/admin/login", requestOrigin(request)));
  return back((result.json as { code?: string } | null)?.code === "meta_state" ? "expired" : "error");
}

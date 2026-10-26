import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";
import { requestOrigin } from "@/lib/requestOrigin";

/**
 * «Connect Instagram and Facebook»: off to Meta's login page, where the
 * person picks the Pages and Instagram accounts to share. Without the
 * platform's Meta keys, back to the social screen.
 */
export async function GET(request: NextRequest) {
  const result = await adminApiFetchRaw("/admin/social/connect", { method: "POST" });
  const url = (result.json as { data?: { url?: string } } | null)?.data?.url;

  if (result.status === 200 && url?.startsWith("https://www.facebook.com/")) {
    return NextResponse.redirect(url);
  }

  const reason =
    result.status === 503 ? "not_configured" : result.status === 401 ? "login" : result.status === 403 ? "forbidden" : "error";
  return NextResponse.redirect(new URL(`/admin/social?meta=${reason}`, requestOrigin(request)));
}

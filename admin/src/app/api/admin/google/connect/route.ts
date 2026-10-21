import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";
import { requestOrigin } from "@/lib/requestOrigin";

/**
 * «Connect Google Calendar»: off to Google's consent page, where the person
 * picks the account. Without the platform's Google keys, back to the calendar.
 */
export async function GET(request: NextRequest) {
  const result = await adminApiFetchRaw("/admin/google/connect", { method: "POST" });
  const url = (result.json as { data?: { url?: string } } | null)?.data?.url;

  if (result.status === 200 && url?.startsWith("https://accounts.google.com/")) {
    return NextResponse.redirect(url);
  }

  const reason = result.status === 503 ? "not_configured" : result.status === 401 ? "login" : "error";
  return NextResponse.redirect(new URL(`/admin/calendar?google=${reason}`, requestOrigin(request)));
}
